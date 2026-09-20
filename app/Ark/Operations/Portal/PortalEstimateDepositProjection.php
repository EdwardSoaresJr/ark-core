<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Financial\BalanceDueResult;
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
     *     authorizationFromSession: bool,
     *     depositCollected: bool,
     *     payingRemaining: bool,
     *     collectionSummary: ?string,
     *     paymentNotice: ?array{
     *         kind: 'complete'|'partial'|'balance_due',
     *         title: string,
     *         body: string|null,
     *         remaining_line: string|null,
     *         received_amount: string|null,
     *     },
     * }
     */
    public function forAccessToken(
        RepairOrder $repairOrder,
        EstimateAccessToken $accessToken,
        ?array $sessionFlash,
    ): array {
        $balance = $repairOrder->balanceDue();
        $unappliedDeposits = $balance->unappliedDepositsCents;
        $payingRemainingBase = $unappliedDeposits > 0;
        $paymentNotice = $this->paymentNotice($repairOrder, $balance);
        $collectionSummary = $this->legacyCollectionSummary($paymentNotice);

        if (is_array($sessionFlash) && isset($sessionFlash['approval_id'])) {
            $payload = $this->withLiveCharge($repairOrder, $sessionFlash);
            $fullyCollected = $this->fullyCollected($payload);

            return [
                'portalAuthorization' => $payload,
                'authorizationFromSession' => true,
                'depositCollected' => $this->paymentReceived(
                    $balance->hasIssuedInvoice,
                    $balance->isPaid(),
                    $fullyCollected && $payingRemainingBase,
                ),
                'payingRemaining' => $payingRemainingBase && ! $fullyCollected,
                'collectionSummary' => $collectionSummary,
                'paymentNotice' => $paymentNotice,
            ];
        }

        $approval = $this->latestPayablePortalApproval($repairOrder);

        if (! $approval instanceof ApprovalEvent) {
            return [
                'portalAuthorization' => null,
                'authorizationFromSession' => false,
                'depositCollected' => $this->paymentReceived(
                    $balance->hasIssuedInvoice,
                    $balance->isPaid(),
                    $payingRemainingBase
                        && $this->depositGuard->remainingAllowedDepositCents($repairOrder, $balance) === 0,
                ),
                'payingRemaining' => false,
                'collectionSummary' => $collectionSummary,
                'paymentNotice' => $paymentNotice,
            ];
        }

        $payload = $this->withLiveCharge($repairOrder, $this->authorizationPayload($repairOrder, $approval));
        $fullyCollected = $this->fullyCollected($payload);

        return [
            'portalAuthorization' => $payload,
            'authorizationFromSession' => false,
            'depositCollected' => $this->paymentReceived(
                $balance->hasIssuedInvoice,
                $balance->isPaid(),
                $fullyCollected && $payingRemainingBase,
            ),
            'payingRemaining' => $payingRemainingBase && ! $fullyCollected,
            'collectionSummary' => $collectionSummary,
            'paymentNotice' => $paymentNotice,
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

    /**
     * "Payment received" requires ledger money. An issued invoice zeros the
     * remaining deposit charge, which is not the same as the customer paying.
     */
    private function paymentReceived(bool $invoiceIssued, bool $invoicePaid, bool $depositCollected): bool
    {
        return $invoiceIssued ? $invoicePaid : $depositCollected;
    }

    /**
     * @return array{
     *     kind: 'complete'|'partial'|'balance_due',
     *     title: string,
     *     body: string|null,
     *     remaining_line: string|null,
     *     received_amount: string|null,
     * }|null
     */
    private function paymentNotice(RepairOrder $repairOrder, BalanceDueResult $balance): ?array
    {
        if ($balance->hasIssuedInvoice) {
            $paidCents = max(0, $balance->paymentsAppliedCents + $balance->depositsAppliedCents);
            $dueCents = $balance->balanceDueCents;

            if ($paidCents > 0 && $dueCents <= 0) {
                return $this->completePaymentNotice($paidCents);
            }

            if ($paidCents > 0 && $dueCents > 0) {
                return $this->partialPaymentNotice(
                    $paidCents,
                    sprintf('%s remaining', Money::ofMinor($dueCents, 'USD')->formatTo('en_US')),
                );
            }

            if ($dueCents > 0) {
                return [
                    'kind' => 'balance_due',
                    'title' => sprintf('Balance due %s.', Money::ofMinor($dueCents, 'USD')->formatTo('en_US')),
                    'body' => null,
                    'remaining_line' => null,
                    'received_amount' => null,
                ];
            }

            return null;
        }

        $paidCents = max(0, $balance->unappliedDepositsCents);
        $dueCents = $this->depositGuard->remainingAllowedDepositCents($repairOrder, $balance);

        if ($paidCents > 0 && $dueCents <= 0) {
            return $this->completePaymentNotice($paidCents);
        }

        if ($paidCents > 0 && $dueCents > 0) {
            return $this->partialPaymentNotice(
                $paidCents,
                sprintf('%s remaining', Money::ofMinor($dueCents, 'USD')->formatTo('en_US')),
            );
        }

        return null;
    }

    /**
     * @return array{
     *     kind: 'complete',
     *     title: string,
     *     body: string,
     *     remaining_line: null,
     *     received_amount: string,
     * }
     */
    private function completePaymentNotice(int $paidCents): array
    {
        $amount = Money::ofMinor($paidCents, 'USD')->formatTo('en_US');

        return [
            'kind' => 'complete',
            'title' => 'Payment received',
            'body' => sprintf('Thank you — we received your %s payment.', $amount),
            'remaining_line' => null,
            'received_amount' => $amount,
        ];
    }

    /**
     * @return array{
     *     kind: 'partial',
     *     title: string,
     *     body: string,
     *     remaining_line: string,
     *     received_amount: string,
     * }
     */
    private function partialPaymentNotice(int $paidCents, string $remainingLine): array
    {
        $amount = Money::ofMinor($paidCents, 'USD')->formatTo('en_US');

        return [
            'kind' => 'partial',
            'title' => 'Partial payment received',
            'body' => sprintf('Thank you — we received your %s payment.', $amount),
            'remaining_line' => $remainingLine,
            'received_amount' => $amount,
        ];
    }

    /**
     * @param  array{
     *     kind: 'complete'|'partial'|'balance_due',
     *     title: string,
     *     body: string|null,
     *     remaining_line: string|null,
     *     received_amount: string|null,
     * }|null  $paymentNotice
     */
    private function legacyCollectionSummary(?array $paymentNotice): ?string
    {
        if ($paymentNotice === null || $paymentNotice['kind'] === 'complete') {
            return null;
        }

        if ($paymentNotice['kind'] === 'balance_due') {
            return $paymentNotice['title'];
        }

        return trim(sprintf(
            '%s %s',
            $paymentNotice['body'] ?? '',
            $paymentNotice['remaining_line'] ?? '',
        ));
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
