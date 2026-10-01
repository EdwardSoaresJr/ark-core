<?php

namespace App\Ark\Operations\Approvals;

final class ApprovalEventStaffPresentation
{
    public static function headline(ApprovalEvent $event): string
    {
        if (($event->approved_amount_cents ?? 0) <= 0) {
            return 'Customer deferred recommended work';
        }

        return $event->approval_type->label().' authorization';
    }

    public static function amountLabel(ApprovalEvent $event): string
    {
        if (($event->approved_amount_cents ?? 0) <= 0) {
            return 'No approved work';
        }

        return 'Approved amount';
    }
}
