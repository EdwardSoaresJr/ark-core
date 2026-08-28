<?php

namespace App\Ark\Operations\Leads;

enum LeadSource: string
{
    case Website = 'website';
    case Call = 'call';
    case Sms = 'sms';
    case Messenger = 'messenger';
    case Manual = 'manual';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Call => 'Call',
            self::Sms => 'SMS',
            self::Messenger => 'Messenger',
            self::Manual => 'Manual',
            self::Unknown => 'Unknown',
        };
    }

    /** Inbox / interrupt origin chip — where the opportunity entered. */
    public function opportunityLabel(): string
    {
        return match ($this) {
            self::Website => 'Website Lead',
            self::Sms => 'SMS Lead',
            self::Call => 'Call Lead',
            self::Messenger => 'Messenger Lead',
            self::Manual => 'Manual Lead',
            self::Unknown => 'Lead',
        };
    }
}
