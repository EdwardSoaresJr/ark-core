<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderBillingPostureController
{
    public function preview(
        Request $request,
        RepairOrder $repairOrder,
        ChangeRepairOrderBillingPostureAction $changeBilling,
    ): JsonResponse {
        $next = $this->validatedPosture($request);
        $plan = $changeBilling->plan($repairOrder, $next);
        $repriced = [];
        $kept = 0;

        foreach ($plan['following'] as $entry) {
            foreach ($entry['plan']['repriced'] as $line) {
                $repriced[] = sprintf(
                    '%s: %s %s → %s/hr',
                    $entry['concern']->summary,
                    $line['line']->description,
                    self::money($line['prior_rate_cents']),
                    self::money($line['new_rate_cents']),
                );
            }

            $kept += count($entry['plan']['skipped']);
        }

        $details = [
            ...$repriced,
            ...array_map(
                fn (RepairOrderConcern $concern): string => $concern->summary.': stays '.$concern->billing_posture->label(),
                $plan['unchanged'],
            ),
        ];

        return response()->json([
            'prior' => $plan['prior']->value,
            'next' => $next->value,
            'concerns_following' => count($plan['following']),
            'concerns_unchanged' => count($plan['unchanged']),
            'labor_lines_repriced' => count($repriced),
            'labor_lines_kept' => $kept,
            'summary' => self::summary($plan['prior'], $next, count($plan['following']), count($repriced), $kept),
            'details' => $details,
        ]);
    }

    public function update(
        Request $request,
        RepairOrder $repairOrder,
        ChangeRepairOrderBillingPostureAction $changeBilling,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse|JsonResponse {
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $result = $changeBilling->execute($repairOrder, $this->validatedPosture($request), $request->user());
        $message = self::summary($result['prior'], $result['next'], $result['concerns_followed'], $result['repriced'], $result['kept'], applied: true);

        if ($request->expectsJson()) {
            return response()->json([
                'billing_posture' => $result['next']->value,
                'concerns_followed' => $result['concerns_followed'],
                'labor_lines_repriced' => $result['repriced'],
                'labor_lines_kept' => $result['kept'],
                'message' => $message,
            ]);
        }

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('estimate-lines')
            ->with('status', $message);
    }

    private function validatedPosture(Request $request): ConcernBillingPosture
    {
        $data = $request->validate([
            'billing_posture' => [
                'required',
                Rule::in(array_map(
                    fn (ConcernBillingPosture $posture): string => $posture->value,
                    ConcernBillingPosture::advisorSelectableCases(),
                )),
            ],
        ]);

        return ConcernBillingPosture::from($data['billing_posture']);
    }

    private static function summary(
        ConcernBillingPosture $prior,
        ConcernBillingPosture $next,
        int $concerns,
        int $repriced,
        int $kept,
        bool $applied = false,
    ): string {
        if ($prior === $next) {
            return 'This visit is already billed '.$next->label().'.';
        }

        $parts = [
            'Visit billing '.($applied ? 'changed' : 'changes').' from '.$prior->label().' to '.$next->label().'.',
            $concerns === 0
                ? 'No concern is on '.$prior->label().' billing.'
                : $concerns.' '.($concerns === 1 ? 'concern' : 'concerns').' on '.$prior->label().' '.($applied ? 'moved' : ($concerns === 1 ? 'moves' : 'move')).' to '.$next->label().'.',
        ];

        if ($repriced > 0) {
            $parts[] = $repriced.' labor '.($repriced === 1 ? 'line' : 'lines').' '.($applied ? 'repriced' : ($repriced === 1 ? 'reprices' : 'reprice')).'.';
        }

        if ($kept > 0) {
            $parts[] = $kept.' labor '.($kept === 1 ? 'line keeps its' : 'lines keep their').' rate (approved, custom or special pricing).';
        }

        $parts[] = 'The customer\'s billing class does not change.';

        return implode(' ', $parts);
    }

    private static function money(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }
}
