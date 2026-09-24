<?php

namespace App\Ark\Operations\Scoreboard;

use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;

/**
 * Customer-facing concern dispositions for dollar close.
 *
 * Draft is the only disposition hidden from the customer. Portal, PDF, email,
 * and print all drop concerns unless visibleToCustomer() is true, and that
 * method is false only for draft. Recommended, approved, deferred, and declined
 * are therefore the presented population. An estimate-sent event is separate
 * evidence that a link went out. It is not required for this rate, because work
 * can be reviewed in person without one.
 */
final class PresentedConcernPopulation
{
    /**
     * @return list<RepairOrderConcernDisposition>
     */
    public static function dispositions(): array
    {
        return array_values(array_filter(
            RepairOrderConcernDisposition::cases(),
            static fn (RepairOrderConcernDisposition $disposition): bool => $disposition->visibleToCustomer(),
        ));
    }

    public static function includes(RepairOrderConcernDisposition $disposition): bool
    {
        return $disposition->visibleToCustomer();
    }

    public static function explanation(): string
    {
        return 'Dollar close is approved pre-tax dollars divided by customer-facing pre-tax dollars on repair orders opened in this period. Draft is excluded. Recommended, deferred, and declined stay in the denominator, so fresh undecided work lowers the rate until the customer decides. ARK treats a concern as customer-facing once it leaves draft: those concerns appear on the estimate, the portal, and printed copies. A sent estimate link is recorded separately and is not required for this rate.';
    }
}
