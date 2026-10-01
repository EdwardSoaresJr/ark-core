<?php

namespace App\Ark\Operations\WorkAuthorization;

enum TestingPackageOutcome: string
{
    case Resolved = 'resolved';
    case RepairRecommended = 'repair_recommended';
    case EscalateTesting = 'escalate_testing';
    case NoFaultFound = 'no_fault_found';
    case CustomerDeclinedFurtherTesting = 'customer_declined_further_testing';

    public function label(): string
    {
        return match ($this) {
            self::Resolved => 'Resolved',
            self::RepairRecommended => 'Repair recommended',
            self::EscalateTesting => 'Escalate testing',
            self::NoFaultFound => 'No fault found',
            self::CustomerDeclinedFurtherTesting => 'Customer declined further testing',
        };
    }
}
