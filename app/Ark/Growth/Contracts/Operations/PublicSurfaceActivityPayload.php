<?php

namespace App\Ark\Growth\Contracts\Operations;

final readonly class PublicSurfaceActivityPayload
{
    /**
     * @param  array<string, mixed>|null  $context
     * @param  array<string, mixed>  $requestMeta
     */
    public function __construct(
        public string $laravelSessionId,
        public string $surfaceEventType,
        public ?int $leadId = null,
        public ?string $attributionChannel = null,
        public ?array $context = null,
        public array $requestMeta = [],
        public ?string $visitorId = null,
    ) {}
}
