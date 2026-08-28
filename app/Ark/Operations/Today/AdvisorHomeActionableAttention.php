<?php

namespace App\Ark\Operations\Today;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Workboard\WorkboardTriageCard;

/**
 * Needs Action is an attention queue — not a lifecycle status bucket.
 */
final class AdvisorHomeActionableAttention
{
    public static function belongsInNeedsAction(
        WorkboardTriageCard $card,
        ?AdvisorHomeCardSurface $surface,
    ): bool {
        if ($card->countsAsOverduePickup) {
            return true;
        }

        return self::reasonFor($card, $surface) !== null;
    }

    public static function reasonFor(
        WorkboardTriageCard $card,
        ?AdvisorHomeCardSurface $surface,
    ): ?string {
        $repairOrder = $card->repairOrder;

        if ($card->countsAsOverduePickup) {
            return 'Overdue pickup';
        }

        if ($surface !== null && in_array($surface->chip->tone, ['alert', 'warn', 'ready'], true)) {
            $label = $surface->chip->label;

            if (! self::isPassiveWaitingReason($label, $repairOrder)) {
                return $label;
            }
        }

        if ($card->countsAsUnassigned) {
            return 'No tech assigned';
        }

        if ($card->countsAsCustomerWaiting) {
            if ($card->signalLabel !== null && trim($card->signalLabel) !== '') {
                $signal = trim($card->signalLabel);

                if (! self::isPassiveWaitingReason($signal, $repairOrder)) {
                    return $signal;
                }
            }

            return 'Customer waiting';
        }

        if ($card->signalLabel !== null && trim($card->signalLabel) !== '') {
            $signal = trim($card->signalLabel);

            if (! self::isPassiveWaitingReason($signal, $repairOrder)) {
                return $signal;
            }
        }

        return self::statusReason($repairOrder);
    }

    private static function statusReason(RepairOrder $repairOrder): ?string
    {
        if ($repairOrder->status->is(RepairOrderStatus::WaitingApproval)) {
            return 'Waiting approval';
        }

        if ($repairOrder->status->is(RepairOrderStatus::Draft)) {
            return 'Needs diagnosis';
        }

        return null;
    }

    private static function isPassiveWaitingReason(string $label, RepairOrder $repairOrder): bool
    {
        $normalized = strtolower(trim($label));

        if ($normalized === '') {
            return true;
        }

        if (str_contains($normalized, 'waiting on parts')
            || str_contains($normalized, 'waiting parts')
            || str_contains($normalized, 'repair order waiting parts')) {
            return true;
        }

        if ($repairOrder->status->is(RepairOrderStatus::WaitingParts)
            && ! self::isExplicitPartsActionReason($normalized)) {
            return true;
        }

        return false;
    }

    private static function isExplicitPartsActionReason(string $normalized): bool
    {
        return str_contains($normalized, 'parts received')
            || str_contains($normalized, 'parts arrived')
            || str_contains($normalized, 'follow up')
            || str_contains($normalized, 'customer waiting')
            || str_contains($normalized, 'vehicle id')
            || str_contains($normalized, 'balance due')
            || str_contains($normalized, 'overdue');
    }
}
