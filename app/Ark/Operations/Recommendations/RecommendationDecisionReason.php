<?php

namespace App\Ark\Operations\Recommendations;

enum RecommendationDecisionReason: string
{
    case Budget = 'budget';
    case NeedsMoreTime = 'needs_more_time';
    case SecondOpinion = 'second_opinion';
    case DoingThemselves = 'doing_themselves';
    case GoingElsewhere = 'going_elsewhere';
    case SellingVehicle = 'selling_vehicle';
    case NotInterested = 'not_interested';
    case Scheduling = 'scheduling';
    case FinancingDeclined = 'financing_declined';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Budget => 'Budget',
            self::NeedsMoreTime => 'Needs more time',
            self::SecondOpinion => 'Wants second opinion',
            self::DoingThemselves => 'Doing it themselves',
            self::GoingElsewhere => 'Going elsewhere',
            self::SellingVehicle => 'Selling vehicle',
            self::NotInterested => 'Not interested',
            self::Scheduling => 'Scheduling / time',
            self::FinancingDeclined => 'Financing declined',
            self::Other => 'Other',
        };
    }

    public static function optionalFrom(?string $value): ?self
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }
}
