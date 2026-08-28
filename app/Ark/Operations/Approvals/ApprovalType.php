<?php

namespace App\Ark\Operations\Approvals;

enum ApprovalType: string
{
    case Diagnostic = 'diagnostic';
    case Repair = 'repair';
    case Partial = 'partial';

    public function label(): string
    {
        return match ($this) {
            self::Diagnostic => 'Diagnostic',
            self::Repair => 'Repair',
            self::Partial => 'Partial',
        };
    }
}
