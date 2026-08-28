<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class DocumentAttachController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        Document $document,
        DocumentAuthorize $authorize,
        AttachDocumentToRepairOrderAction $attach,
    ): RedirectResponse {
        $authorize->assertBelongsToCustomer($customer, $document);

        $data = $request->validate([
            'repair_order_id' => ['required', 'integer'],
        ]);

        $repairOrder = RepairOrder::query()->findOrFail((int) $data['repair_order_id']);
        $attach->handle($document, $repairOrder, $request->user());

        if ($request->boolean('return_to_ro')) {
            return redirect()
                ->route('operations.repair-orders.show', $repairOrder)
                ->with('status', 'Document attached to this repair order.');
        }

        return redirect()
            ->route('operations.customers.show', $customer)
            ->withFragment('customer-documents')
            ->with('status', 'Document attached to RO #'.$repairOrder->repair_order_id.'.');
    }
}
