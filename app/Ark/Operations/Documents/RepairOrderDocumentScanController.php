<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RepairOrderDocumentScanController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        StoreScannedDocumentAction $store,
    ): RedirectResponse {
        $repairOrder->loadMissing('customer');
        abort_unless($repairOrder->customer !== null, 404);

        $data = $request->validate([
            'pages' => ['required', 'array', 'min:1', 'max:40'],
            'pages.*' => DocumentStore::pageUploadRules(required: true),
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(DocumentType::class)],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $type = $data['type'] instanceof DocumentType
            ? $data['type']
            : DocumentType::from((string) $data['type']);

        $store->handle(
            $repairOrder->customer,
            array_values($data['pages']),
            $request->user(),
            $type,
            $data['title'],
            $data['description'] ?? null,
            $repairOrder,
        );

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->with('status', 'Document saved.');
    }
}
