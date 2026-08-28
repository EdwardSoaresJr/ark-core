<?php

namespace App\Ark\Operations\Leads\Public;

enum PublicSurfaceEventType: string
{
    case SurfaceViewed = 'surface_viewed';
    case FormExposed = 'form_exposed';
    case LeadStarted = 'lead_started';
    case LeadSubmitted = 'lead_submitted';
    case LeadCreated = 'lead_created';
    case CallClicked = 'call_clicked';
    case TextClicked = 'text_clicked';
    case CommonProblemLinkClicked = 'common_problem_link_clicked';

    public function clientRecordable(): bool
    {
        return match ($this) {
            self::SurfaceViewed,
            self::LeadStarted,
            self::CallClicked,
            self::TextClicked,
            self::CommonProblemLinkClicked => true,
            self::LeadSubmitted,
            self::LeadCreated => false,
        };
    }

    public function dedupePerSession(): bool
    {
        return match ($this) {
            self::SurfaceViewed,
            self::FormExposed,
            self::LeadStarted => true,
            default => false,
        };
    }
}
