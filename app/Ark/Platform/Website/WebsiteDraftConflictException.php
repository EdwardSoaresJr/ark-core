<?php

namespace App\Ark\Platform\Website;

use RuntimeException;

final class WebsiteDraftConflictException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $liveDocument
     */
    public function __construct(
        public readonly string $acknowledgedCoreHash,
        public readonly string $liveCoreHash,
        public readonly array $liveDocument,
        string $message = 'Core website content changed since the last acknowledged revision.',
    ) {
        parent::__construct($message);
    }
}
