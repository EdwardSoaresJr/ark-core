<?php

namespace App\Ark\Operations\ArkManager;

final readonly class ArkManagerCommunicationDraft
{
    public function __construct(
        public string $channel,
        public string $subject,
        public string $body,
        public string $source,
        public bool $aiEnhanced,
    ) {}
}
