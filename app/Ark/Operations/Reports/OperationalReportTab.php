<?php

namespace App\Ark\Operations\Reports;

enum OperationalReportTab: string
{
    case Operations = 'operations';
    case MarginHealth = 'margin-health';
    case OwnerPl = 'owner-pl';
    case Financial = 'financial';
    case Production = 'production';

    public function label(): string
    {
        return match ($this) {
            self::Operations => 'Operations',
            self::MarginHealth => 'Margin Health',
            self::OwnerPl => 'Owner P&L',
            self::Financial => 'Financial',
            self::Production => 'Production',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Operations => 'Queue pressure, approval drag, labor liability, and recommendation conversion',
            self::MarginHealth => 'Parts margin, ELR, ARO, and sales mix vs Demo Auto Repair targets',
            self::OwnerPl => 'Management P&L from posted sales, operating income estimate, and tax posture',
            self::Financial => 'Posted RO summary, payments reconciliation, financial mix, and recent posts',
            self::Production => 'Live pressure, advisor throughput, and technician production',
        };
    }

    public static function resolve(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Operations;
    }

    /**
     * @return list<self>
     */
    public static function casesInDisplayOrder(): array
    {
        return [
            self::Operations,
            self::MarginHealth,
            self::OwnerPl,
            self::Financial,
            self::Production,
        ];
    }
}
