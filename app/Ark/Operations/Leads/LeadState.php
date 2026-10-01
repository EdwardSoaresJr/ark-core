<?php

namespace App\Ark\Operations\Leads;

enum LeadState: string
{
    case Received = 'received';
    case Contacted = 'contacted';
    case WaitingCustomer = 'waiting_customer';
    case Scheduled = 'scheduled';
    case Arrived = 'arrived';
    case Converted = 'converted';
    case Lost = 'lost';
    case Spam = 'spam';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'New',
            self::Contacted => 'Contacted',
            self::WaitingCustomer => 'Waiting on customer',
            self::Scheduled => 'Scheduled',
            self::Arrived => 'Arrived',
            self::Converted => 'Converted',
            self::Lost => 'Lost',
            self::Spam => 'Spam',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Converted, self::Lost, self::Spam], true);
    }

    /**
     * @return list<self>
     */
    public static function openCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $state): bool => $state->isOpen(),
        ));
    }
}
