<?php

namespace App\Ark\Platform\Website;

use RuntimeException;

final class WebsiteDraftDivergesFromCoreException extends RuntimeException
{
    public function __construct(
        public readonly string $draftHash,
        public readonly string $coreHash,
    ) {
        parent::__construct('The draft does not match the live website.');
    }
}
