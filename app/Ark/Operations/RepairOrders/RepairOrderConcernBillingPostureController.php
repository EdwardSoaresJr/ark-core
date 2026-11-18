<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\EstimatePricing\CanonicalBillingClassCatalog;
use App\Ark\Operations\EstimatePricing\SynchronizeRepairOrderBillingMinimums;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepairOrderConcernBillingPostureController
{
    /** Posted value that clears an override so the concern follows its customer's billing class. */
    public const INHERIT = 'inherit';

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderConcern $concern,
        ChangeConcernBillingPostureAction $changeBilling,
        RepairOrderConcurrency $concurrency,
        SynchronizeRepairOrderBillingMinimums $billingMinimums,
    ): RedirectResponse|JsonResponse {
        abort_unless($concern->repair_order_id === $repairOrder->id, 404);

        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $settings = ShopSettings::current();
        // Selectable: the one catalog, plus what the concern already bills as (so a re-post never fails).
        $allowed = collect(CanonicalBillingClassCatalog::selectableConcernLaborColumnKeys($settings))
            ->push($concern->billingClassKey())
            ->push(self::INHERIT)
            ->unique()
            ->values()
            ->all();

        $data = $request->validate([
            'billing_posture' => ['required', 'string', Rule::in($allowed)],
        ]);

        if ($data['billing_posture'] === self::INHERIT) {
            $result = $changeBilling->inherit($repairOrder, $concern, $request->user());
        } else {
            $next = ConcernBillingPosture::tryFrom($data['billing_posture']) ?? $data['billing_posture'];

            $result = $changeBilling->execute(
                $repairOrder,
                $concern,
                $next,
                $request->user(),
            );
        }

        $concern->refresh();
        $billingMinimums->execute($repairOrder);

        if ($request->expectsJson()) {
            return response()->json([
                'billing_posture' => $concern->billingClassKey(),
                'effective_billing_class' => $concern->effectiveBillingClassKey(),
                'inherits_customer' => $concern->inheritsCustomerBilling(),
                'label' => $concern->billingClassLabel(),
                'labor_lines_repriced' => $result['repriced'],
                'labor_lines_kept' => $result['kept'],
            ]);
        }

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('estimate-lines')
            ->with('status', self::statusMessage($concern, $result));
    }

    /**
     * @param  array{changed: bool, repriced: int, kept: int}  $result
     */
    private static function statusMessage(RepairOrderConcern $concern, array $result): string
    {
        $message = 'Billing updated for '.$concern->summary.'.';

        if ($result['repriced'] > 0) {
            $message .= ' '.$result['repriced'].' labor line'.($result['repriced'] === 1 ? '' : 's').' repriced at the '.$concern->billingClassLabel().' rate.';
        }

        if ($result['kept'] > 0) {
            $message .= ' '.$result['kept'].' labor line'.($result['kept'] === 1 ? '' : 's').' kept '.($result['kept'] === 1 ? 'its' : 'their').' rate (approved, custom or special pricing).';
        }

        return $message;
    }
}
