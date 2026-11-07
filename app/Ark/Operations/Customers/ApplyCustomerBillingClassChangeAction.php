<?php

namespace App\Ark\Operations\Customers;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Financial\RefreshCustomerInvoiceAction;
use App\Ark\Operations\RepairOrders\RecordsRepairOrderEstimateMutation;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Models\User;

/**
 * Re-prices a customer's open repair orders after their billing class changed. Standing discounts
 * follow the customer, so these totals move; each repair order whose total changed gets a new
 * estimate version, a customer_billing_class_changed event and an updated invoice.
 */
final class ApplyCustomerBillingClassChangeAction
{
    use RecordsRepairOrderEstimateMutation;

    public function __construct(
        private readonly EstimateTotalsCalculator $calculator,
        private readonly OperationalEventRecorder $events,
        private readonly RefreshCustomerInvoiceAction $refreshInvoice,
    ) {}

    public function execute(Customer $customer, ?string $priorBillingClass, ?User $actor = null): void
    {
        $customer->repairOrders()
            ->where('status', '!=', RepairOrderStatus::Closed->value)
            ->with(['lines.concern', 'customer', 'concerns'])
            ->each(function (RepairOrder $repairOrder) use ($customer, $priorBillingClass, $actor): void {
                $priorTotalCents = $this->calculator->totalsFor($repairOrder)->totalCents();

                $this->calculator->recalculateRepairOrder($repairOrder);

                $repairOrder = $repairOrder->fresh(['lines.concern', 'customer', 'concerns']);
                $newTotalCents = $this->calculator->totalsFor($repairOrder)->totalCents();

                if ($newTotalCents === $priorTotalCents) {
                    return;
                }

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
