<?php

namespace App\Ark\Operations\Payments\Capture;

use Brick\Money\Money;

/**
 * Transient reader state for one terminal capture attempt.
 * Settlement starts only after the attempt succeeds and the ledger is written.
 */
final class TerminalPaymentProgress
{
    /**
     * @return array{
     *     phase: string,
     *     headline: string,
     *     note: string,
     *     detail: string,
     *     tone: string,
     *     spinning: bool,
     *     blocks_form: bool,
     *     amount: string,
     *     refresh_url: string,
     *     cancel_url: string
     * }|null
     */
    public static function payload(PaymentCaptureAttempt $attempt): ?array
    {
        $view = self::describe($attempt);
        if ($view === null) {
            return null;
        }

        $repairOrder = $attempt->repairOrder;
        if ($repairOrder === null) {
            return null;
        }

        $view['amount'] = self::amount($attempt);
        $view['refresh_url'] = route('operations.repair-orders.payment-capture.refresh', [
            'repairOrder' => $repairOrder,
            'attempt' => $attempt,
        ]);
        $view['cancel_url'] = self::canCancel($attempt)
            ? route('operations.repair-orders.payment-capture.cancel', [
                'repairOrder' => $repairOrder,
                'attempt' => $attempt,
            ])
            : '';

        return $view;
    }

    public static function flash(PaymentCaptureAttempt $attempt): ?string
    {
        $view = self::describe($attempt);
        if ($view === null || $view['spinning']) {
            return null;
        }

        $headline = $view['headline'];
        $detail = $view['detail'];

        return $detail !== '' ? $headline.'. '.$detail : $headline.'.';
    }

    /**
     * @return array{
     *     phase: string,
     *     headline: string,
     *     note: string,
     *     detail: string,
     *     tone: string,
     *     spinning: bool,
     *     blocks_form: bool
     * }|null
     */
    public static function describe(PaymentCaptureAttempt $attempt): ?array
    {
        if ($attempt->capture_method !== PaymentCaptureMethod::Terminal) {
            return null;
        }

        return match ($attempt->status) {
            PaymentCaptureAttemptStatus::Pending,
            PaymentCaptureAttemptStatus::Accepted => filled($attempt->provider_payment_id)
                ? self::view(
                    'processing',
                    'Processing payment',
                    'The customer has started payment.',
                    'Waiting for the result.',
                    'waiting',
                    true,
                    true,
                )
                : self::view(
                    'waiting',
                    'Waiting for customer',
                    'Payment sent to reader',
                    'Waiting for the customer to complete payment.',
                    'waiting',
                    true,
                    true,
                ),
            PaymentCaptureAttemptStatus::Succeeded => self::view(
                'approved',
                'Payment approved',
                '',
                'The payment is recorded.',
                'approved',
                false,
                false,
            ),
            PaymentCaptureAttemptStatus::Cancelled => self::view(
                'canceled',
                'Payment canceled',
                '',
                'No payment was recorded.',
                'declined',
                false,
                false,
            ),
            PaymentCaptureAttemptStatus::Failed => self::failed($attempt->failure_reason),
            PaymentCaptureAttemptStatus::ReconciliationRequired => self::view(
                'reconciliation',
                'Payment needs review',
                '',
                'Do not take this payment again until it is resolved.',
                'review',
                false,
                true,
            ),
        };
    }

    /**
     * @return array{
     *     phase: string,
     *     headline: string,
     *     note: string,
     *     detail: string,
     *     tone: string,
     *     spinning: bool,
     *     blocks_form: bool
     * }
     */
    private static function failed(?string $reason): array
    {
        $reason = strtolower((string) $reason);

        if (in_array($reason, [
            'cloud_unavailable',
            'provider_unavailable',
            'missing_device',
            'entitlement_unavailable',
            'provider_error',
        ], true)) {
            return self::view(
                'unavailable',
                'Reader unavailable',
                '',
                'The reader did not take the payment. Try again.',
                'declined',
                false,
                false,
            );
        }

        return self::view(
            'declined',
            'Payment declined',
            '',
            'No payment was recorded.',
            'declined',
            false,
            false,
        );
    }

    /**
     * @return array{
     *     phase: string,
     *     headline: string,
     *     note: string,
     *     detail: string,
     *     tone: string,
     *     spinning: bool,
     *     blocks_form: bool
     * }
     */
    private static function view(
        string $phase,
        string $headline,
        string $note,
        string $detail,
        string $tone,
        bool $spinning,
        bool $blocksForm,
    ): array {
        return [
            'phase' => $phase,
            'headline' => $headline,
            'note' => $note,
            'detail' => $detail,
            'tone' => $tone,
            'spinning' => $spinning,
            'blocks_form' => $blocksForm,
        ];
    }

    private static function canCancel(PaymentCaptureAttempt $attempt): bool
    {
        if ($attempt->hasLedgerEntry() || ! $attempt->exists) {
            return false;
        }

        return in_array($attempt->status, [
            PaymentCaptureAttemptStatus::Pending,
            PaymentCaptureAttemptStatus::Accepted,
        ], true);
    }

    private static function amount(PaymentCaptureAttempt $attempt): string
    {
        $currency = filled($attempt->currency) ? (string) $attempt->currency : 'USD';

        return '$'.Money::ofMinor($attempt->amount_cents, $currency)->getAmount()->toScale(2)->__toString();
    }
}
