<?php

namespace App\Ark\Import;

final class LegacyImportOptions
{
    public function __construct(
        public bool $dryRun = true,
        public ?int $limit = null,
        public ?int $legacyCustomerId = null,
        public bool $wipeImported = false,
        public bool $resume = false,
    ) {}
}
