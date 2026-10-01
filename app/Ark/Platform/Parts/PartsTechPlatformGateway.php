<?php

namespace App\Ark\Platform\Parts;

final class PartsTechPlatformGateway
{
    /** @var array<string, mixed>|null */
    private ?array $readiness = null;

    public function __construct(
        private readonly ArkPartsClient $parts,
    ) {}

    public function usesPlatform(): bool
    {
        return ManagedPartsGate::platformCatalog();
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(): array
    {
        if (! $this->usesPlatform()) {
            return [
                'ok' => false,
                'ready' => false,
                'reason_code' => 'not_platform',
                'message' => 'PartsTech Catalog is not using ARK Platform.',
            ];
        }

        return $this->readiness ??= $this->parts->readiness();
    }

    public function isReady(): bool
    {
        $readiness = $this->readiness();

        return ($readiness['ok'] ?? false) === true
            && ($readiness['ready'] ?? false) === true;
    }

    public function blockedReason(): string
    {
        $readiness = $this->readiness();
        $code = (string) ($readiness['reason_code'] ?? '');

        return match ($code) {
            'not_entitled' => 'PartsTech Catalog isn\'t enabled for this shop.',
            'parts_catalog_not_ready' => 'PartsTech Catalog needs setup on ARK Platform.',
            'provider_unavailable' => 'PartsTech Catalog is unavailable.',
            default => is_string($readiness['message'] ?? null) && $readiness['message'] !== ''
                ? (string) $readiness['message']
                : 'PartsTech Catalog is unavailable.',
        };
    }
}
