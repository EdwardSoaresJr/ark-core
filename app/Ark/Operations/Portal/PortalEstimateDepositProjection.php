<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Financial\RepairOrderDefaultDepositCalculator;
use App\Ark\Operations\Financial\RepairOrderDepositRecordingGuard;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Brick\Money\Money;

/**
 * Resolves portal deposit / remaining-balance pay state from authority — not session flash alone.
 *
 * Session flash still wins immediately after authorize; on refresh we rebuild
 * from the latest portal approval so customers can pay the deposit and any leftover.
 */
final class PortalEstimateDepositProjection
{
    public function __construct(
        private readonly RepairOrderDefaultDepositCalculator $defaultDepositCalculator,
        private readonly RepairOrderDepositRecordingGuard $depositGuard,
    ) {}

    /**
     * @param  array<string, mixed>|null  $sessionFlash
     * @return array{
     *     portalAuthorization: array<string, mixed>|null,
     *     depositCollected: bool,
     *     payingRemaining: bool,
     * }
     */
    public function forAccessToken(
        RepairOrder $repairOrder,
        EstimateAccessToken $accessToken,
        ?array $sessionFlash,
    ): array {
        $unappliedDeposits = $repairOrder->balanceDue()->unappliedDepositsCents;
        $payingRemainingBase = $unappliedDeposits > 0;

        if (is_array($sessionFlash) && isset($sessionFlash['approval_id'])) {
            $payload = $this->withLiveCharge($repairOrder, $sessionFlash);
            $fullyCollected = $this->fullyCollected($payload);

            return [
                'portalAuthorization' => $payload,
                'depositCollected' => $fullyCollected && $payingRemainingBase,
                'payingRemaining' => $payingRemainingBase && ! $fullyCollected,
            ];
        }

        $approval = $this->latestPayablePortalApproval($repairOrder);

        if (! $approval instanceof ApprovalEvent) {
            return [
                'portalAuthorization' => null,
                'depositCollected' => $payingRemainingBase
                    && $this->depositGuard->remainingAllowedDepositCents($repairOrder) === 0,
                'payingRemaining' => false,
            ];
        }

        $payload = $this->withLiveCharge($repairOrder, $this->authorizationPayload($repairOrder, $approval));
        $fullyCollected = $this->fullyCollected($payload);

        return [
            'portalAuthorization' => $payload,
            'depositCollected' => $fullyCollected,
            'payingRemaining' => $payingRemainingBase && ! $fullyCollected,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withLiveCharge(RepairOrder $repairOrder, array $payload): array
    {
        $approvedCents = (int) ($payload['approved_amount_cents'] ?? 0);
        $chargeCents = $this->depositGuard->portalChargeCents($repairOrder, $approvedCents);

        $payload['deposit_amount_cents'] = $chargeCents;
        $payload['deposit_amount'] = $chargeCents > 0
            ? Money::ofMinor($chargeCents, 'USD')->formatTo('en_US')
            : ($payload['deposit_amount'] ?? null);

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function fullyCollected(?array $payload): bool
    {
        return $payload === null || (int) ($payload['deposit_amount_cents'] ?? 0) <= 0;
    }

    private function latestPayablePortalApproval(RepairOrder $repairOrder): ?ApprovalEvent
    {
        $repairOrder->loadMissing('approvalEvents.revocation');

        return $repairOrder->approvalEvents
            ->filter(fn (ApprovalEvent $event): bool => $event->source === ApprovalSource::Portal
                && ! $event->isRevoked()
                && $event->approved_amount_cents > 0)
            ->sortByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function authorizationPayload(RepairOrder $repairOrder, ApprovalEvent $approval): array
    {
        $depositAmountCents = $this->defaultDepositCalculator->portalDepositCents(
            $repairOrder,
            $approval->approved_amount_cents,
        );

        return [
            'approval_id' => $approval->id,
            'approved_amount_cents' => $approval->approved_amount_cents,
            'approved_amount' => Money::ofMinor($approval->approved_amount_cents, 'USD')->formatTo('en_US'),
            'deposit_amount_cents' => $depositAmountCents,
            'deposit_amount' => Money::ofMinor($depositAmountCents, 'USD')->formatTo('en_US'),
            'approved_by' => $approval->approved_by,
            'approved_at_label' => ShopDisplayTimezone::format($approval->approved_at),
            'source' => $approval->source->value,
        ];
    }
}
