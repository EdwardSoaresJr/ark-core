<?php

namespace App\Ark\Operations\RepairOrders;

use App\Models\User;

/**
 * After estimate delivery: Draft|Estimate → Waiting Approval when the catalog allows.
 * Returns an explicit outcome so send surfaces can toast / explain silent skips.
 */
final class MarkEstimateAwaitingCustomerApprovalAction
{
    public function __construct(
        private readonly RepairOrderLifecycleTransition $lifecycle,
    ) {}

    /**
     * @return array{
     *     moved: bool,
     *     from_status: string,
     *     to_status: string|null,
     *     reason: string,
     *     blocking_message: string|null,
     *     toast: string|null,
     * }
     */
    public function execute(RepairOrder $repairOrder, ?User $actor = null): array
    {
        $repairOrder->refresh();

        $fromStatus = $repairOrder->status->value;
        $fromLabel = $repairOrder->statusDisplayLabel();
        $target = RepairOrderStatus::WaitingApproval->value;
        $targetLabel = RepairOrderStatus::WaitingApproval->label();

        if ($repairOrder->isTerminal()) {
            return $this->outcome(
                moved: false,
                fromStatus: $fromStatus,
                toStatus: null,
                reason: 'terminal',
                blockingMessage: null,
                toast: null,
            );
        }

        if (RepairOrderStatus::isWaitingCustomerApproval($repairOrder->status)) {
            return $this->outcome(
                moved: false,
                fromStatus: $fromStatus,
                toStatus: $target,
                reason: 'already_waiting',
                blockingMessage: null,
                toast: null,
            );
        }

        if (! $repairOrder->status->is(RepairOrderStatus::Draft) && ! $repairOrder->status->is(RepairOrderStatus::Estimate)) {
            return $this->outcome(
                moved: false,
                fromStatus: $fromStatus,
                toStatus: null,
                reason: 'not_source_status',
                blockingMessage: null,
                toast: 'Estimate sent. Status stayed '.$fromLabel.'.',
            );
        }

        $blocking = $this->lifecycle->blockingReason($repairOrder, $target, $actor);

        if ($blocking !== null) {
            return $this->outcome(
                moved: false,
                fromStatus: $fromStatus,
                toStatus: null,
                reason: 'blocked',
                blockingMessage: $blocking,
                toast: 'Estimate sent. Status stayed '.$fromLabel.' — '.$blocking,
            );
        }

        $this->lifecycle->move($repairOrder, $target, $actor);

        return $this->outcome(
            moved: true,
            fromStatus: $fromStatus,
            toStatus: $target,
            reason: 'moved',
            blockingMessage: null,
            toast: 'Moved to '.$targetLabel.'.',
        );
    }

    /**
     * Prefer a successful move when SMS + email both invoke the marker.
     *
     * @param  array{moved: bool, from_status: string, to_status: string|null, reason: string, blocking_message: string|null, toast: string|null}|null  $preferred
     * @param  array{moved: bool, from_status: string, to_status: string|null, reason: string, blocking_message: string|null, toast: string|null}  $next
     * @return array{moved: bool, from_status: string, to_status: string|null, reason: string, blocking_message: string|null, toast: string|null}
     */
    public static function prefer(?array $preferred, array $next): array
    {
        if ($preferred === null) {
            return $next;
        }

        if (($preferred['moved'] ?? false) === true) {
            return $preferred;
        }

        if (($next['moved'] ?? false) === true) {
            return $next;
        }

        return $preferred;
    }

    /**
     * @return array{
     *     moved: bool,
     *     from_status: string,
     *     to_status: string|null,
     *     reason: string,
     *     blocking_message: string|null,
     *     toast: string|null,
     * }
     */
    private function outcome(
        bool $moved,
        string $fromStatus,
        ?string $toStatus,
        string $reason,
        ?string $blockingMessage,
        ?string $toast,
    ): array {
        return [
            'moved' => $moved,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'blocking_message' => $blockingMessage,
            'toast' => $toast,
        ];
    }
}
