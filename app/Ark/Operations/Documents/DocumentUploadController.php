<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class DocumentUploadController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        StoreDocumentAction $store,
    ): RedirectResponse {
        $data = $request->validate([
            'file' => DocumentStore::uploadRules(required: true),
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(DocumentType::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'repair_order_id' => ['nullable', 'integer'],
        ]);

        $repairOrder = null;
        if (! empty($data['repair_order_id'])) {
            $repairOrder = RepairOrder::query()->findOrFail((int) $data['repair_order_id']);
        }

        $type = $data['type'] instanceof DocumentType
            ? $data['type']
            : DocumentType::from((string) $data['type']);

        $store->handle(
            $customer,
            $data['file'],
            $request->user(),
            $type,
            $data['title'],
            $data['description'] ?? null,
            $repairOrder,
            DocumentSource::Upload,
        );

        if ($repairOrder !== null && $request->boolean('return_to_ro')) {
            return redirect()->route('operations.repair-orders.show', $repairOrder)
                ->with('status', 'Document saved.');
        }

        return redirect()->route('operations.customers.show', $customer)
            ->withFragment('customer-documents')
            ->with('status', 'Document saved.');
    }
}
