<?php

namespace App\Ark\Operations\Recommendations;

enum RecommendationEventType: string
{
    case Presented = 'presented';
    case Approved = 'approved';
    case Declined = 'declined';
    case Deferred = 'deferred';
    case AddedToEstimate = 'added_to_estimate';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
    case FollowUpSet = 'follow_up_set';
    case FollowUpCompleted = 'follow_up_completed';
    case FollowUpSnoozed = 'follow_up_snoozed';

    public function label(): string
    {
        return match ($this) {
            self::Presented => 'Presented',
            self::Approved => 'Authorized',
            self::Declined => 'Declined',
            self::Deferred => 'Deferred',
            self::AddedToEstimate => 'Added to estimate',
            self::Resolved => 'Resolved',
            self::Dismissed => 'Dismissed',
            self::FollowUpSet => 'Follow-up scheduled',
            self::FollowUpCompleted => 'Follow-up completed',
            self::FollowUpSnoozed => 'Follow-up snoozed',
        };
    }

    public function isDecision(): bool
    {
        return in_array($this, [self::Approved, self::Declined, self::Deferred], true);
    }
}
