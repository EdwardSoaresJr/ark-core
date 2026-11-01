<?php

namespace App\Ark\Operations\Approvals;

/**
 * Each approval event as it was recorded: its own type and its own amount, never today's totals.
 */
final class ApprovalEventStaffPresentation
{
    public static function headline(ApprovalEvent $event): string
    {
        if (self::isDeferredResponse($event)) {
            return 'Customer deferred recommended work';
        }

        return $event->approval_type->label().' authorization';
    }

    public static function amountLabel(ApprovalEvent $event): string
    {
        return match (true) {
            self::recordedAmountCents($event) > 0 => 'Approved amount',
            self::isDeferredResponse($event) => 'No approved work',
            default => 'Amount not recorded',
        };
    }

    public static function recordedAmountCents(ApprovalEvent $event): int
    {
        return (int) ($event->approved_amount_cents ?? 0);
    }

    /** Only a partial response with no approved amount is a deferral (the portal records one when nothing is approved). */
    public static function isDeferredResponse(ApprovalEvent $event): bool
    {
        return $event->approval_type === ApprovalType::Partial && self::recordedAmountCents($event) <= 0;
    }
}
