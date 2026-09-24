<?php

namespace App\Ark\Operations\Financial;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentPaidAt;
use App\Ark\Operations\RepairOrders\WorksheetMutationIdempotency;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

final class FinancialSubmissionIntentGate
{
    public const FIELD = WorksheetMutationIdempotency::FIELD;

    public function paidOn(?string $date): ?string
    {
        $paidAt = RepairOrderPaymentPaidAt::fromDateInput($date);

        if ($paidAt === null) {
            return null;
        }

        return $paidAt->timezone(ShopDisplayTimezone::resolve())->toDateString();
    }

    public function fingerprint(
        FinancialSubmissionOperation $operation,
        RepairOrder $repairOrder,
        int $amountCents,
        PaymentMethod $method,
        ?string $reference,
        ?string $paidOn,
    ): string {
        $payload = [
            'amount_cents' => $amountCents,
            'operation' => $operation->value,
            'paid_on' => $paidOn,
            'payment_method' => $method->value,
            'reference' => $reference,
            'repair_order_id' => (int) $repairOrder->id,
        ];

        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function claim(
        RepairOrder $repairOrder,
        FinancialSubmissionOperation $operation,
        ?string $intentKey,
        string $fingerprint,
    ): FinancialSubmissionClaim {
        $intentKey = $this->normalize($intentKey);

        if ($intentKey === null) {
            return FinancialSubmissionClaim::unguarded();
        }

        $existing = $this->locked($intentKey);

        if ($existing !== null) {
            return $this->fromExisting($existing, $repairOrder, $operation, $fingerprint);
        }

        return FinancialSubmissionClaim::pending($intentKey, $fingerprint);
    }

    public function reserve(
        FinancialSubmissionClaim $claim,
        RepairOrder $repairOrder,
        FinancialSubmissionOperation $operation,
    ): FinancialSubmissionClaim {
        if ($claim->replayed || $claim->intentKey === null || $claim->intent !== null) {
            return $claim;
        }

        $fingerprint = (string) $claim->fingerprint;

        try {
            $intent = $this->insert($repairOrder, $operation, $claim->intentKey, $fingerprint);
        } catch (UniqueConstraintViolationException) {
            $existing = $this->locked($claim->intentKey);

            if ($existing === null) {
                $intent = $this->insert($repairOrder, $operation, $claim->intentKey, $fingerprint);
            } else {
                return $this->fromExisting($existing, $repairOrder, $operation, $fingerprint);
            }
        }

        return FinancialSubmissionClaim::proceed($intent);
    }

    public function complete(FinancialSubmissionClaim $claim, RepairOrderLedgerEntry $entry): void
    {
        if ($claim->replayed || $claim->intent === null) {
            return;
        }

        $claim->intent->forceFill([
            'ledger_entry_id' => $entry->id,
        ])->save();
    }

    private function normalize(?string $intentKey): ?string
    {
        $intentKey = strtolower(trim((string) $intentKey));

        if ($intentKey === '') {
            return null;
        }

        return $intentKey;
    }

    private function locked(string $intentKey): ?FinancialSubmissionIntent
    {
        return FinancialSubmissionIntent::query()
            ->where('intent_key', $intentKey)
            ->lockForUpdate()
            ->first();
    }

    private function insert(
        RepairOrder $repairOrder,
        FinancialSubmissionOperation $operation,
        string $intentKey,
        string $fingerprint,
    ): FinancialSubmissionIntent {
        return FinancialSubmissionIntent::query()->create([
            'intent_key' => $intentKey,
            'repair_order_id' => $repairOrder->id,
            'operation' => $operation,
            'payload_fingerprint' => $fingerprint,
        ]);
    }

    private function fromExisting(
        FinancialSubmissionIntent $existing,
        RepairOrder $repairOrder,
        FinancialSubmissionOperation $operation,
        string $fingerprint,
    ): FinancialSubmissionClaim {
        if ((int) $existing->repair_order_id !== (int) $repairOrder->id) {
            throw ValidationException::withMessages([
                self::FIELD => 'This submission does not belong to this repair order.',
            ]);
        }

        $sameOperation = $existing->operation === $operation;
        $samePayload = strlen((string) $existing->payload_fingerprint) === strlen($fingerprint)
            && hash_equals((string) $existing->payload_fingerprint, $fingerprint);

        if (! $sameOperation || ! $samePayload) {
            throw ValidationException::withMessages([
                self::FIELD => 'This submission was already recorded with different details.',
            ]);
        }

        if ($existing->ledger_entry_id === null) {
            throw ValidationException::withMessages([
                self::FIELD => 'This submission did not finish. Refresh the page and record it again.',
            ]);
        }

        return FinancialSubmissionClaim::replay($existing);
    }
}
