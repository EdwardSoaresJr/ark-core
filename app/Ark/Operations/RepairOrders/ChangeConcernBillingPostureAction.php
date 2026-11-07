<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\RefreshCustomerInvoiceAction;
use App\Ark\Operations\Labor\LaborAuthority;
use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Changes a concern's billing and re-prices the labor that was priced by labor policy, together.
 *
 * Labor keeps its rate when it carries a manual or menu rate, a customer approval covers it,
 * it is courtesy, comeback or zero-rate work, or its stored pricing does not show that the labor
 * policy priced it.
 */
final class ChangeConcernBillingPostureAction
{
    use RecordsRepairOrderEstimateMutation;

    public const SKIP_MANUAL_RATE = 'manual_rate';

    public const SKIP_APPROVED = 'customer_approved';

    public const SKIP_SPECIAL_PRICING = 'special_pricing';

    public const SKIP_UNKNOWN_PRICING = 'pricing_source_unknown';

    public const SKIP_PRICING_UNAVAILABLE = 'policy_unavailable';

    private const SPECIAL_LABOR_CATEGORY_KEYS = ['courtesy', ShopSettings::COMEBACK_LABOR_CATEGORY_KEY];

    public function __construct(
        private readonly LaborAuthority $laborAuthority,
        private readonly WorkCompletionAuthorization $authorization,
        private readonly EstimateTotalsCalculator $calculator,
        private readonly OperationalEventRecorder $events,
        private readonly RefreshCustomerInvoiceAction $refreshInvoice,
    ) {}

    /**
     * What the change would do, without changing anything.
     *
     * @return array{
     *     prior: ConcernBillingPosture,
     *     next: ConcernBillingPosture,
     *     repriced: list<array{line: RepairOrderLine, attributes: array<string, mixed>, prior_rate_cents: int, new_rate_cents: int, prior_category: string|null, new_category: string}>,
     *     skipped: list<array{line: RepairOrderLine, reason: string}>
     * }
     */
    public function plan(RepairOrderConcern $concern, ConcernBillingPosture $next): array
    {
        $settings = ShopSettings::current();
        $prior = $concern->billing_posture;
        $priorCategory = $settings->laborDefaultsForBillingPosture($prior)['category_key'];
        $nextCategory = $settings->laborDefaultsForBillingPosture($next)['category_key'];
        $repriced = [];
        $skipped = [];

        $lines = $concern->lines()
            ->where('type', RepairOrderLineType::Labor->value)
            ->orderBy('id')
            ->get();

        foreach ($lines as $line) {
            $reason = $this->keepReason($line->setRelation('concern', $concern));

            if ($reason !== null) {
                $skipped[] = ['line' => $line, 'reason' => $reason];

                continue;
            }

            $categoryKey = (string) $line->labor_category_key;

            if ($categoryKey === $priorCategory
                && $priorCategory !== $nextCategory
                && ! $settings->laborCategoryAllowsModifiers($categoryKey)) {
                $categoryKey = $nextCategory;
            }

            try {
                $attributes = $this->laborAuthority->repriceForBillingChange($line, $next, $categoryKey, $settings);
            } catch (RuntimeException) {
                $skipped[] = ['line' => $line, 'reason' => self::SKIP_PRICING_UNAVAILABLE];

                continue;
            }

            $repriced[] = [
                'line' => $line,
                'attributes' => $attributes,
                'prior_rate_cents' => (int) $line->unit_price_cents,
                'new_rate_cents' => (int) $attributes['unit_price_cents'],
                'prior_category' => $line->labor_category_key,
                'new_category' => $categoryKey,
            ];
        }

        return ['prior' => $prior, 'next' => $next, 'repriced' => $repriced, 'skipped' => $skipped];
    }

    /**
     * @return array{changed: bool, repriced: int, kept: int}
     */
    public function execute(
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        ConcernBillingPosture $next,
        ?User $actor = null,
        string $source = 'concern',
    ): array {
        if ($concern->billing_posture === $next) {
            return ['changed' => false, 'repriced' => 0, 'kept' => 0];
        }

        $plan = DB::transaction(function () use ($repairOrder, $concern, $next, $actor, $source): array {
            $plan = $this->plan($concern, $next);

            $concern->update(['billing_posture' => $next]);

            foreach ($plan['repriced'] as $entry) {
                $attributes = $entry['attributes'];
                $quantity = $attributes['quantity'] ?? (string) $entry['line']->quantity;

                $entry['line']->update([
                    'quantity' => $quantity,
                    'unit_price_cents' => $attributes['unit_price_cents'],
                    'subtotal_cents' => $this->calculator->lineTotalCents($quantity, $attributes['unit_price_cents']),
                    ...array_intersect_key(
                        RepairOrderLineLaborInput::persistenceAttributes($attributes),
                        $attributes,
                    ),
                ]);
            }

            $this->calculator->recalculateRepairOrder($repairOrder);

            $this->events->record(
                OperationalEventName::ConcernBillingChanged,
                $repairOrder,
                actor: $actor,
                payload: [
                    'concern_id' => $concern->id,
                    'source' => $source,
                    'prior_billing_posture' => $plan['prior']->value,
                    'new_billing_posture' => $next->value,
                    'repriced' => array_map(fn (array $entry): array => [
                        'line_id' => $entry['line']->id,
                        'prior_rate_cents' => $entry['prior_rate_cents'],
                        'new_rate_cents' => $entry['new_rate_cents'],
                        'prior_category' => $entry['prior_category'],
                        'new_category' => $entry['new_category'],
                    ], $plan['repriced']),
                    'kept' => array_map(fn (array $entry): array => [
                        'line_id' => $entry['line']->id,
                        'rate_cents' => (int) $entry['line']->unit_price_cents,
                        'reason' => $entry['reason'],
                    ], $plan['skipped']),
                ],
            );

            return $plan;
        });

        $this->recordRepairOrderEstimateMutation($repairOrder, $actor);
        $this->refreshInvoice->executeIfNeeded(
            $repairOrder->fresh(['concerns', 'lines.concern', 'customer']),
            $actor,
            'billing_changed',
        );

        return ['changed' => true, 'repriced' => count($plan['repriced']), 'kept' => count($plan['skipped'])];
    }

    private function keepReason(RepairOrderLine $line): ?string
    {
        if (filled($line->labor_rate_override_reason) || $line->labor_rate_overridden_at !== null) {
            return self::SKIP_MANUAL_RATE;
        }

        if (in_array((string) $line->labor_category_key, self::SPECIAL_LABOR_CATEGORY_KEYS, true)
            || (int) $line->unit_price_cents === 0) {
            return self::SKIP_SPECIAL_PRICING;
        }

        if ($line->labor_policy_id === null
            || $line->labor_rate_cents === null
            || (int) $line->labor_rate_cents !== (int) $line->unit_price_cents
            || $line->policy_resolved_labor_rate_cents === null
            || (int) $line->policy_resolved_labor_rate_cents !== (int) $line->unit_price_cents) {
            return self::SKIP_UNKNOWN_PRICING;
        }

        if ($this->authorization->customerApprovalCovers($line)) {
            return self::SKIP_APPROVED;
        }

        return null;
    }
}
