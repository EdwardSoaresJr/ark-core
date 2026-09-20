<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class RepairOrderDocumentAttachController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        AttachDocumentToRepairOrderAction $attach,
    ): RedirectResponse {
        $data = $request->validate([
            'document_id' => ['required', 'integer'],
        ]);

        $document = Document::query()->findOrFail((int) $data['document_id']);
        abort_unless((int) $document->customer_id === (int) $repairOrder->customer_id, 404);
        abort_unless($document->isActive(), 404);

        $attach->handle($document, $repairOrder, $request->user());

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->with('status', 'Document attached to this repair order.');
    }
}
