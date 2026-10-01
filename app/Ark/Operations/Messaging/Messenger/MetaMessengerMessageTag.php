<?php

namespace App\Ark\Operations\Messaging\Messenger;

enum MetaMessengerMessageTag: string
{
    case ConfirmedEventUpdate = 'CONFIRMED_EVENT_UPDATE';
    case PostPurchaseUpdate = 'POST_PURCHASE_UPDATE';
    case AccountUpdate = 'ACCOUNT_UPDATE';

    public function label(): string
    {
        return match ($this) {
            self::ConfirmedEventUpdate => 'Appointment / vehicle update',
            self::PostPurchaseUpdate => 'Post-service update',
            self::AccountUpdate => 'Account update',
        };
    }

    public function operationalHint(): string
    {
        return match ($this) {
            self::ConfirmedEventUpdate => 'Use for appointment reminders and confirmed pickup or drop-off updates.',
            self::PostPurchaseUpdate => 'Use after service is complete for status or invoice follow-up.',
            self::AccountUpdate => 'Use for account-level notices allowed by Meta policy.',
        };
    }
}
