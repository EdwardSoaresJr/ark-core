<?php

namespace App\Ark\Operations\RepairOrders;

enum RepairOrderLostReason: string
{
    case NoResponse = 'no_response';
    case PriceDeclined = 'price_declined';
    case VehicleSold = 'vehicle_sold';
    case WentElsewhere = 'went_elsewhere';
    case DiagnosticOnly = 'diagnostic_only';
    case NotEconomical = 'not_economical';
    case DuplicateCleanup = 'duplicate_cleanup';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NoResponse => 'No response',
            self::PriceDeclined => 'Price declined',
            self::VehicleSold => 'Vehicle sold',
            self::WentElsewhere => 'Went elsewhere',
            self::DiagnosticOnly => 'Diagnostic only',
            self::NotEconomical => 'Not economical to repair',
            self::DuplicateCleanup => 'Duplicate / cleanup',
            self::Other => 'Other',
        };
    }

    public function requiresNote(): bool
    {
        return $this === self::Other;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $reason): array => [
                'value' => $reason->value,
                'label' => $reason->label(),
            ],
            self::cases(),
        );
    }
}
