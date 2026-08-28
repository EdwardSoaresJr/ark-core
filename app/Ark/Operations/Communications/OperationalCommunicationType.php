<?php

namespace App\Ark\Operations\Communications;

enum OperationalCommunicationType: string
{
    case EstimateSent = 'estimate_sent';
    case InvoiceSent = 'invoice_sent';
    case EstimateViewed = 'estimate_viewed';
    case ApprovalFollowUp = 'approval_follow_up';
    case CustomerReply = 'customer_reply';
    case PickupNotified = 'pickup_notified';
    case CustomerUnreachable = 'customer_unreachable';
    case AdvisorNote = 'advisor_note';
    case MessageDelivered = 'message_delivered';
    case MessageRead = 'message_read';
    case SmsOptOut = 'sms_opt_out';
    case SmsOptIn = 'sms_opt_in';
    case SmsDelivered = 'sms_delivered';
    case SmsDeliveryFailed = 'sms_delivery_failed';

    public function label(): string
    {
        return match ($this) {
            self::EstimateSent => 'Estimate sent',
            self::InvoiceSent => 'Invoice sent',
            self::EstimateViewed => 'Estimate viewed',
            self::ApprovalFollowUp => 'Approval follow-up',
            self::CustomerReply => 'Customer replied',
            self::PickupNotified => 'Pickup notified',
            self::CustomerUnreachable => 'Customer unreachable',
            self::AdvisorNote => 'Advisor note',
            self::MessageDelivered => 'Message delivered',
            self::MessageRead => 'Message read',
            self::SmsOptOut => 'SMS opt-out',
            self::SmsOptIn => 'SMS opt-in',
            self::SmsDelivered => 'SMS delivered',
            self::SmsDeliveryFailed => 'SMS delivery failed',
        };
    }

    /** Carrier delivery receipts — tracked on the event row, not advisor comms scan surfaces. */
    public function surfacesOnAdvisorCommsTimeline(): bool
    {
        return ! in_array($this, [self::SmsDelivered, self::SmsDeliveryFailed], true);
    }
}
