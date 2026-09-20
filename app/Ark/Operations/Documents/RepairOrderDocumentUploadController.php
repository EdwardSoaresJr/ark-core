<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RepairOrderDocumentUploadController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        StoreDocumentAction $store,
    ): RedirectResponse {
        $repairOrder->loadMissing('customer');
        abort_unless($repairOrder->customer !== null, 404);

        $data = $request->validate([
            'file' => DocumentStore::uploadRules(required: true),
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(DocumentType::class)],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $type = $data['type'] instanceof DocumentType
            ? $data['type']
            : DocumentType::from((string) $data['type']);

        $store->handle(
            $repairOrder->customer,
            $data['file'],
            $request->user(),
            $type,
            $data['title'],
            $data['description'] ?? null,
            $repairOrder,
            DocumentSource::Upload,
        );

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->with('status', 'Document saved.');
    }
}
