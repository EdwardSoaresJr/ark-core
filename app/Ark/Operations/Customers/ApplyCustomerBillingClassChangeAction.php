<?php

namespace App\Ark\Operations\Customers;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\RefreshCustomerInvoiceAction;
use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\WorkCompletionAuthorization;
use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;

/**
 * Re-prices a customer's open repair orders after their billing class changed.
 *
 * Explicit advisor action on the customer record — not a shop Settings edit.
 * Open ROs restamp commercial_pricing_profile from current shop settings, then recalculate.
 *
 * Approved-dollar protection: invoice refresh uses approvedTotals / approved line portions
 * (`WorkCompletionAuthorization`), not raw post-recalc line totals. When recalculation
 * increases the RO total after approved work exists, the event is flagged
 * `requires_additional_authorization` so advisors know more customer approval is needed.
 * Approved invoice dollars are never silently expanded by this action.
 */
final class ApplyCustomerBillingClassChangeAction
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly OperationalEventRecorder $events,
        private readonly RefreshCustomerInvoiceAction $refreshInvoice,
        private readonly WorkCompletionAuthorization $authorization,
    ) {}

    public function execute(Customer $customer, ?string $priorBillingClass, ?User $actor = null): void
    {
        $settings = ShopSettings::current();

        $customer->repairOrders()
            ->where('status', '!=', RepairOrderStatus::Closed->value)
            ->with(['lines.concern', 'customer', 'concerns'])
            ->each(function (RepairOrder $repairOrder) use ($customer, $priorBillingClass, $actor, $settings): void {
                $priorTotalCents = $this->calculator->totalsFor($repairOrder)->totalCents();
                $hadApprovedWork = $repairOrder->concerns->contains(
                    fn ($concern): bool => $concern->disposition === RepairOrderConcernDisposition::Approved,
                );
                $priorAuthorizedLineIds = $hadApprovedWork
                    ? $this->authorization->customerApprovedLaborAndPartIdSet($repairOrder)
                    : [];

                // Adopt current commercial rules for the newly selected customer class.
                $repairOrder->restampCommercialPricingProfile($settings);
                $repairOrder->refresh();

                $this->calculator->recalculateRepairOrder($repairOrder);

                $repairOrder = $repairOrder->fresh(['lines.concern', 'customer', 'concerns']);
                $newTotalCents = $this->calculator->totalsFor($repairOrder)->totalCents();

                if ($newTotalCents === $priorTotalCents) {
                    return;
                }

                $requiresAdditionalAuthorization = $hadApprovedWork && $newTotalCents > $priorTotalCents;

                $this->events->record(
                    OperationalEventName::CustomerBillingClassChanged,
                    $repairOrder,
                    actor: $actor,
                    payload: [
                        'customer_id' => $customer->id,
                        'prior_billing_class' => $priorBillingClass,
                        'new_billing_class' => $customer->customer_type,
                        'prior_total_cents' => $priorTotalCents,
                        'new_total_cents' => $newTotalCents,
                        'had_approved_work' => $hadApprovedWork,
                        'requires_additional_authorization' => $requiresAdditionalAuthorization,
                        'approved_line_count' => count($priorAuthorizedLineIds),
                        'source' => 'explicit_customer_billing_class_change',
                    ],
                );

                $this->recordRepairOrderEstimateMutation($repairOrder, $actor);
                $this->refreshInvoice->executeIfNeeded(
                    $repairOrder->fresh(['concerns', 'lines.concern', 'customer']),
                    $actor,
                    'customer_billing_class_changed',
                );
            });
    }
}
