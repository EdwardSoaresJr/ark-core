<?php

namespace App\Ark\Operations\Approvals;

enum ApprovalSource: string
{
    case Phone = 'phone';
    case Sms = 'sms';
    case Portal = 'portal';
    case Email = 'email';
    case InPerson = 'in_person';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone',
            self::Sms => 'SMS',
            self::Portal => 'Portal',
            self::Email => 'Email',
            self::InPerson => 'In person',
        };
    }
}
