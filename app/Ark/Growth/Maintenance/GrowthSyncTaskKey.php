<?php

namespace App\Ark\Growth\Maintenance;

enum GrowthSyncTaskKey: string
{
    case SearchConsole = 'search_console';
    case GoogleBusinessProfile = 'google_business_profile';
    case PublicContent = 'public_content';
    case OpportunityQueue = 'opportunity_queue';
    case SeoAudit = 'seo_audit';
    case AutoPublish = 'auto_publish';
    case SearchEngineNotify = 'search_engine_notify';
    case Briefing = 'briefing';

    public function label(): string
    {
        return match ($this) {
            self::SearchConsole => 'Search Console',
            self::GoogleBusinessProfile => 'Google Business Profile',
            self::OpportunityQueue => 'Opportunity Queue',
            self::PublicContent => 'Public content registry',
            self::SeoAudit => 'SEO audit',
            self::AutoPublish => 'Auto-publish pages',
            self::SearchEngineNotify => 'Search engine notify',
            self::Briefing => 'Operations briefing',
        };
    }
}
