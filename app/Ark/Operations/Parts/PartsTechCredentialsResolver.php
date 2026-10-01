<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Models\User;

final class PartsTechCredentialsResolver
{
    public function __construct(
        private readonly ShopIntegrationCredentials $shop,
    ) {}

    public function forUser(?User $user): PartsTechLoginCredentials
    {
        if ($user !== null) {
            $username = trim((string) $user->partstech_username);

            if ($username !== '' && filled($user->partstech_password)) {
                return new PartsTechLoginCredentials(
                    username: $username,
                    password: (string) $user->partstech_password,
                    baseUrl: $this->shop->partsTechBaseUrl(),
                    sessionIdentity: 'user:'.$user->id,
                    source: 'user',
                );
            }
        }

        return new PartsTechLoginCredentials(
            username: (string) ($this->shop->partsTechUsername() ?? ''),
            password: (string) ($this->shop->partsTechPassword() ?? ''),
            baseUrl: $this->shop->partsTechBaseUrl(),
            sessionIdentity: 'shop',
            source: 'shop',
        );
    }

    public function quoteImportConfigured(?User $user): bool
    {
        return $this->forUser($user)->configured();
    }
}
