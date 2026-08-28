<?php

namespace App\Ark\Growth\Seo\Audit;

enum SeoAuditChannel: string
{
    case Structural = 'structural';
    case Runtime = 'runtime';

    public function label(): string
    {
        return match ($this) {
            self::Structural => 'Structural',
            self::Runtime => 'Runtime',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Structural => 'Derived from application configuration and public surface authorities — instant after deploy.',
            self::Runtime => 'Requires a live crawl of public pages — refreshed on nightly maintenance.',
        };
    }
}
