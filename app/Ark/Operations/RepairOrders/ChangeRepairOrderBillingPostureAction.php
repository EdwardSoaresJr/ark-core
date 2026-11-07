<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sets this visit's billing. Concerns still on the visit's previous billing follow it; concerns
 * with a different billing keep theirs. The customer's billing class and other repair orders
 * are not touched.
 *
 * Concerns do not record whether staff chose their billing or it came from the visit, so a
 * concern deliberately set to the previous billing follows the change too.
 */
final class ChangeRepairOrderBillingPostureAction
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly ChangeConcernBillingPostureAction $concernBilling,
        private readonly OperationalEventRecorder $events,
    ) {}

    /**
     * @return array{
     *     prior: ConcernBillingPosture,
     *     next: ConcernBillingPosture,
     *     following: list<array{concern: RepairOrderConcern, plan: array<string, mixed>}>,
     *     unchanged: list<RepairOrderConcern>
     * }
     */
    public function plan(RepairOrder $repairOrder, ConcernBillingPosture $next): array
    {
        $prior = $repairOrder->defaultBillingPosture();
        $following = [];
        $unchanged = [];

        foreach ($repairOrder->concerns()->orderBy('position')->orderBy('id')->get() as $concern) {
            if ($prior !== $next && $concern->billing_posture === $prior) {
                $following[] = ['concern' => $concern, 'plan' => $this->concernBilling->plan($concern, $next)];
            } else {
                $unchanged[] = $concern;
            }
        }

        return ['prior' => $prior, 'next' => $next, 'following' => $following, 'unchanged' => $unchanged];
    }

    /**
     * @return array{prior: ConcernBillingPosture, next: ConcernBillingPosture, concerns_followed: int, repriced: int, kept: int}
     */
    public function execute(RepairOrder $repairOrder, ConcernBillingPosture $next, ?User $actor = null): array
    {
        $repairOrder->ensureOpenForEditing();

        $result = DB::transaction(function () use ($repairOrder, $next, $actor): array {
            $plan = $this->plan($repairOrder, $next);
            $repriced = 0;
            $kept = 0;

            $repairOrder->update(['default_billing_posture' => $next->value]);

            foreach ($plan['following'] as $entry) {
                $concernResult = $this->concernBilling->execute($repairOrder, $entry['concern'], $next, $actor, 'repair_order');
                $repriced += $concernResult['repriced'];
                $kept += $concernResult['kept'];
            }

            $this->events->record(
                OperationalEventName::RepairOrderBillingChanged,
                $repairOrder,
                actor: $actor,
                payload: [
                    'prior_billing_posture' => $plan['prior']->value,
                    'new_billing_posture' => $next->value,
                    'followed_concern_ids' => array_map(fn (array $entry): int => (int) $entry['concern']->id, $plan['following']),
                    'unchanged_concern_ids' => array_map(fn (RepairOrderConcern $concern): int => (int) $concern->id, $plan['unchanged']),
                    'labor_lines_repriced' => $repriced,
                    'labor_lines_kept' => $kept,
                ],
            );

            return [
                'prior' => $plan['prior'],
                'next' => $next,
                'concerns_followed' => count($plan['following']),
                'repriced' => $repriced,
                'kept' => $kept,
            ];
        });

        $this->recordRepairOrderEstimateMutation($repairOrder, $actor);

        return $result;
    }
}
