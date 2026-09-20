<?php

namespace App\Ark\Operations\RepairOrders;

/**
 * Audience dialect for concept language projections.
 *
 * Same operational concept, different vocabulary per participant.
 * ARK translates between dialects — it does not replace them.
 */
enum ScopeLanguageAudience: string
{
    case Customer = 'customer';
    case Advisor = 'advisor';
    case Technician = 'technician';
    case Invoice = 'invoice';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer wording',
            self::Advisor => 'Advisor wording',
            self::Technician => 'Technician wording',
            self::Invoice => 'Invoice wording',
        };
    }
}
