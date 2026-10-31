<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\WorkCompletionAuthorization;
use Brick\Money\Money;
use Illuminate\Support\Collection;

final class PortalEstimateAuthorization
{
    public function __construct(
        private readonly WorkCompletionAuthorization $workAuthorization,
    ) {}

    /**
     * @return Collection<int, RepairOrderConcern>
     */
    public function pendingRecommendedConcerns(RepairOrder $repairOrder): Collection
    {
        $repairOrder->loadMissing('concerns');

        return $repairOrder->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Recommended)
            ->sortBy('position')
            ->values();
    }

    public function authorizationMode(RepairOrder $repairOrder): PortalEstimateAuthorizationMode
    {
        if ($repairOrder->isTerminal()) {
            return PortalEstimateAuthorizationMode::None;
        }

        if ($this->pendingRecommendedConcerns($repairOrder)->isNotEmpty()) {
            return PortalEstimateAuthorizationMode::PerConcern;
        }

        return PortalEstimateAuthorizationMode::None;
    }

    public function canAuthorize(RepairOrder $repairOrder): bool
    {
        return $this->authorizationMode($repairOrder) !== PortalEstimateAuthorizationMode::None;
    }

    public function latestRecordedApproval(RepairOrder $repairOrder): ?ApprovalEvent
    {
        $repairOrder->loadMissing('approvalEvents.revocation');

        return $repairOrder->approvalEvents
            ->filter(fn (ApprovalEvent $event): bool => ! $event->isRevoked())
            ->sortByDesc('id')
            ->first();
    }

    public function presentedWorkIsFullyApproved(RepairOrder $repairOrder): bool
    {
        $presented = $this->presentedConcerns($repairOrder);

        if ($presented->isEmpty()) {
            return false;
        }

        return $presented->every(
            fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Approved,
        ) && $this->approvedWorkAwaitingApproval($repairOrder) === [];
    }

    /**
     * Work on approved repairs that changed after the customer approved it:
     * added lines at their total, raised lines at the increase.
     *
     * @return list<array{description: string, is_increase: bool, amount_cents: int, amount: string}>
     */
    public function approvedWorkAwaitingApproval(RepairOrder $repairOrder): array
    {
        if ($repairOrder->isTerminal()) {
            return [];
        }

        $raised = $this->workAuthorization->approvedSubtotalsBeforeIncrease($repairOrder);

        return $this->workAuthorization->linesRequiringAuthorization($repairOrder)
            ->map(function (RepairOrderLine $line) use ($repairOrder, $raised): array {
                $amountCents = $this->workAuthorization->pendingApprovalCents($repairOrder, $line);

                return [
                    'description' => filled($line->customer_description)
                        ? (string) $line->customer_description
                        : (string) $line->description,
                    'is_increase' => isset($raised[(int) $line->id]),
                    'amount_cents' => $amountCents,
                    'amount' => Money::ofMinor($amountCents, 'USD')->formatTo('en_US'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Lines the customer approved that the shop has since taken off the estimate.
     *
     * @return list<array{description: string, amount: string}>
     */
    public function approvedWorkRemovedSinceApproval(RepairOrder $repairOrder): array
    {
        return array_map(fn (array $line): array => [
            'description' => $line['customer_description'] !== '' ? $line['customer_description'] : $line['description'],
            'amount' => Money::ofMinor($line['subtotal_cents'], 'USD')->formatTo('en_US'),
        ], $this->workAuthorization->approvedLinesRemoved($repairOrder));
    }

    /**
     * @return Collection<int, RepairOrderConcern>
     */
    public function presentedConcerns(RepairOrder $repairOrder): Collection
    {
        $repairOrder->loadMissing('concerns');

        return $repairOrder->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition->visibleToCustomer()
                && in_array($concern->disposition, [
                    RepairOrderConcernDisposition::Recommended,
                    RepairOrderConcernDisposition::Approved,
                ], true))
            ->sortBy('position')
            ->values();
    }
}
