<?php

namespace App\Ark\Operations\Parts;

/**
 * PartsTech shop login used for GraphQL cart prep and quote import.
 *
 * Each ARK user may have a personal PartsTech seat; otherwise the shop default applies.
 */
final readonly class PartsTechLoginCredentials
{
    public function __construct(
        public string $username,
        public string $password,
        public string $baseUrl,
        public string $sessionIdentity,
        public string $source = 'shop',
    ) {}

    public function configured(): bool
    {
        return $this->baseUrl !== ''
            && filled($this->username)
            && filled($this->password);
    }

    public function usesPersonalLogin(): bool
    {
        return $this->source === 'user';
    }
}
