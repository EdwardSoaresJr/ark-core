<?php

namespace App\Ark\Platform\Website;

use RuntimeException;

final class WebsiteDraftRevisionConflictException extends RuntimeException
{
    public function __construct(
        public readonly int $expectedRevision,
        public readonly int $actualRevision,
    ) {
        parent::__construct(
            "Draft revision mismatch: expected {$expectedRevision}, actual {$actualRevision}.",
        );
    }
}
