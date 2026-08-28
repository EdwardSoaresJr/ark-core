<?php

namespace App\Ark\Operations\Leads;

enum LeadContactPreference: string
{
    case Text = 'text';
    case Call = 'call';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Call => 'Call',
            self::Email => 'Email',
        };
    }

    public function formLabel(): string
    {
        return match ($this) {
            self::Text => 'Text me',
            self::Call => 'Call me',
            self::Email => 'Email me',
        };
    }

    public function outreachLabel(): string
    {
        return match ($this) {
            self::Text => 'Prefers text',
            self::Call => 'Prefers call',
            self::Email => 'Prefers email',
        };
    }
}
