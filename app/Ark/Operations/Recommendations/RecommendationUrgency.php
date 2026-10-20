<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\RepairOrders\RecommendationPriority;

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

    public static function fromPriority(?RecommendationPriority $priority): self
    {
        return match ($priority) {
            RecommendationPriority::MustDo => self::Now,
            RecommendationPriority::ShouldDo => self::Soon,
            default => self::Maintenance,
        };
    }
}
