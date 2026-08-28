<?php

namespace App\Ark\Growth\Integrations\Google;

use App\Ark\Growth\Settings\GrowthIntegrationSettings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GoogleBusinessProfileLocationLister
{
    private const SCOPE = 'https://www.googleapis.com/auth/business.manage';

    public function __construct(
        private readonly GoogleServiceAccountAccessToken $tokens,
    ) {}

    /**
     * @param  array<string, mixed>|null  $credentials  When null, uses saved shop credentials.
     * @return list<array{id: string, name: string, title: string, address: string|null}>
     */
    public function listLocations(?array $credentials = null): array
    {
        $credentials ??= GrowthIntegrationSettings::current()->googleServiceAccountCredentials();

        if ($credentials === null) {
            throw new RuntimeException('Save a Google service account JSON first, or paste JSON and click Discover again.');
        }

        $token = $this->tokens->accessToken(
            $credentials,
            self::SCOPE,
            'growth:google:gbp:access_token',
        );

        if ($token === null) {
            throw new RuntimeException('Unable to obtain a Google access token. Check the service account JSON.');
        }

        $accountsResponse = Http::withToken($token)
            ->get('https://mybusinessaccountmanagement.googleapis.com/v1/accounts');

        if (! $accountsResponse->successful()) {
            throw GoogleGrowthApiException::fromResponse(
                'list Business Profile accounts',
                $accountsResponse->status(),
                $accountsResponse->body(),
            );
        }

        $locations = [];

        foreach ($accountsResponse->json('accounts') ?? [] as $account) {
            $accountName = (string) ($account['name'] ?? '');
            if ($accountName === '') {
                continue;
            }

            $locationsResponse = Http::withToken($token)
                ->get('https://mybusinessbusinessinformation.googleapis.com/v1/'.$accountName.'/locations', [
                    'readMask' => 'name,title,storefrontAddress',
                    'pageSize' => 100,
                ]);

            if (! $locationsResponse->successful()) {
                throw GoogleGrowthApiException::fromResponse(
                    'list locations for '.$accountName,
                    $locationsResponse->status(),
                    $locationsResponse->body(),
                );
            }

            foreach ($locationsResponse->json('locations') ?? [] as $location) {
                $resource = (string) ($location['name'] ?? '');
                if ($resource === '') {
                    continue;
                }

                $address = $location['storefrontAddress'] ?? null;
                $addressLine = is_array($address)
                    ? trim(implode(', ', array_filter([
                        implode(' ', (array) ($address['addressLines'] ?? [])),
                        (string) ($address['locality'] ?? ''),
                        (string) ($address['administrativeArea'] ?? ''),
                        (string) ($address['postalCode'] ?? ''),
                    ])))
                    : null;

                $locations[] = [
                    'id' => $resource,
                    'name' => $resource,
                    'title' => (string) ($location['title'] ?? 'Untitled location'),
                    'address' => $addressLine !== '' ? $addressLine : null,
                ];
            }
        }

        return $locations;
    }
}
