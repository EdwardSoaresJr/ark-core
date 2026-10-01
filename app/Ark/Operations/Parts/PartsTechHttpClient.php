<?php

namespace App\Ark\Operations\Parts;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Authenticated HTTP access to PartsTech shop API and GraphQL.
 */
final class PartsTechHttpClient
{
    private ?CookieJar $cookieJar = null;

    private bool $loggedIn = false;

    public function __construct(
        private readonly PartsTechLoginCredentials $credentials,
    ) {}

    public function configured(): bool
    {
        return $this->credentials->configured();
    }

    public function loginUsername(): string
    {
        return $this->credentials->username;
    }

    public function usesPersonalLogin(): bool
    {
        return $this->credentials->usesPersonalLogin();
    }

    public function login(): void
    {
        if ($this->loggedIn) {
            return;
        }

        $response = $this->request()
            ->post($this->baseUrl().'/api/login', [
                'username' => $this->credentials->username,
                'password' => $this->credentials->password,
                'rememberMe' => true,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatApiErrors($response, 'PartsTech login failed.'));
        }

        $this->loggedIn = true;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function graphql(string $query, array $variables = [], bool $retryOnUnauthorized = true): array
    {
        $payload = ['query' => $query];

        if ($variables !== []) {
            $payload['variables'] = $variables;
        }

        $response = $this->request()->post($this->baseUrl().'/graphql', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('PartsTech GraphQL request failed.');
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException('PartsTech GraphQL returned an invalid response.');
        }

        if (filled($body['errors'] ?? null)) {
            $message = collect($body['errors'])
                ->pluck('message')
                ->filter()
                ->implode(' ');

            if ($retryOnUnauthorized && str_contains(strtolower($message), 'unauthorized')) {
                $this->loggedIn = false;
                $this->login();

                return $this->graphql($query, $variables, false);
            }

            throw new RuntimeException($message !== '' ? $message : 'PartsTech GraphQL returned errors.');
        }

        return $body;
    }

    private function baseUrl(): string
    {
        return $this->credentials->baseUrl;
    }

    private function request(): PendingRequest
    {
        return Http::timeout(20)
            ->acceptJson()
            ->asJson()
            ->withOptions([
                'cookies' => $this->cookieJar(),
            ]);
    }

    private function cookieJar(): CookieJar
    {
        if ($this->cookieJar !== null) {
            return $this->cookieJar;
        }

        $host = parse_url($this->baseUrl(), PHP_URL_HOST);

        $this->cookieJar = CookieJar::fromArray([], is_string($host) ? $host : '');

        return $this->cookieJar;
    }

    private function formatApiErrors(Response $response, string $fallback): string
    {
        $errors = $response->json('errors');

        if (! is_array($errors)) {
            return $fallback;
        }

        $message = collect($errors)
            ->pluck('message')
            ->filter()
            ->implode(' ');

        return $message !== '' ? $message : $fallback;
    }
}
