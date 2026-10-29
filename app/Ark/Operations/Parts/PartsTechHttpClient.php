<?php

namespace App\Ark\Operations\Parts;

use RuntimeException;

/**
 * Stock Core does not call PartsTech. Private SaaS binds the catalog client.
 */
class PartsTechHttpClient
{
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
        throw new RuntimeException('PartsTech is not part of this shop installation.');
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function graphql(string $query, array $variables = [], bool $retryOnUnauthorized = true): array
    {
        throw new RuntimeException('PartsTech is not part of this shop installation.');
    }
}
