<?php

namespace App\Ark\Growth\Seo\Audit;

enum SeoAuditAuthoritySource: string
{
    case Configuration = 'configuration';
    case Registry = 'registry';
    case Crawl = 'crawl';
    case SearchConsole = 'search_console';

    public function label(): string
    {
        return match ($this) {
            self::Configuration => 'Configuration',
            self::Registry => 'Content registry',
            self::Crawl => 'Runtime crawl',
            self::SearchConsole => 'Search Console',
        };
    }

    public function healsOnDeploy(): bool
    {
        return match ($this) {
            self::Configuration => true,
            self::Registry => true,
            self::Crawl, self::SearchConsole => false,
        };
    }
}
