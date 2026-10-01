<?php

namespace App\Ark\Operations\Today\Lifecycle;

enum TodayCompletionEvent: string
{
    case FollowUpLogged = 'follow_up_logged';
    case CustomerReplied = 'customer_replied';
    case EstimateApproved = 'estimate_approved';
    case EstimateDeclined = 'estimate_declined';
    case ReminderScheduled = 'reminder_scheduled';
    case PartReceived = 'part_received';
    case PartInstalled = 'part_installed';

    public function label(): string
    {
        return match ($this) {
            self::FollowUpLogged => 'Follow-up recorded',
            self::CustomerReplied => 'Customer replied',
            self::EstimateApproved => 'Estimate approved',
            self::EstimateDeclined => 'Estimate declined',
            self::ReminderScheduled => 'Reminder scheduled',
            self::PartReceived => 'Part received',
            self::PartInstalled => 'Part installed',
        };
    }
}
