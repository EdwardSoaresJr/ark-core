<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\RecommendationIntent;

enum RecommendationUrgency: string
{
    case Now = 'now';
    case Soon = 'soon';
    case Maintenance = 'maintenance';
    case Diagnostic = 'diagnostic';

    public function label(): string
    {
        return match ($this) {
            self::Now => 'Due now',
            self::Soon => 'Soon',
            self::Maintenance => 'Maintenance',
            self::Diagnostic => 'Diagnostic',
        };
    }

    public static function fromIntent(?RecommendationIntent $intent): self
    {
        return match ($intent) {
            RecommendationIntent::ImmediateAttention => self::Now,
            RecommendationIntent::PlanSoon => self::Soon,
            RecommendationIntent::Diagnostic => self::Diagnostic,
            default => self::Maintenance,
        };
    }
}
