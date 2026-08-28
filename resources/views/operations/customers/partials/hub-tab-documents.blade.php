@php
    $customerDocuments = $customerDocuments ?? collect();
    $documentTypeFilter = request('doc_type');
    $documentQuery = request('doc_q');
    $hasDocuments = $customerDocuments->isNotEmpty();
@endphp

<div id="customer-documents" class="scroll-mt-6">
    @if ($hasDocuments)
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-3 py-2">
            <div>
                <p class="ops-eyebrow">Documents</p>
                <p class="ops-meta mt-0.5">Warranty contracts, registration, outside invoices, alignment sheets — paperwork for this customer.</p>
            </div>
            @can(App\Ark\Runtime\Authorization\ArkCapability::CustomersManage->value)
                <button
                    type="button"
                    class="inline-flex min-h-9 items-center rounded-sm border border-slate-800 bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800"
                    @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'hub-document', invokeEl: $event.currentTarget } }))"
                >
                    + Add Document
                </button>
            @endcan
        </div>

        <form method="GET" action="{{ route('operations.customers.show', $customer) }}" class="flex flex-wrap items-end gap-2 border-b border-slate-100 px-3 py-2">
            <input type="hidden" name="tab" value="documents">
            <label class="block text-[11px] font-medium text-slate-600">
                Search
                <input type="search" name="doc_q" value="{{ $documentQuery }}" class="mt-0.5 block w-48 rounded-md border border-slate-300 px-2 py-1 text-sm" placeholder="Title">
            </label>
            <label class="block text-[11px] font-medium text-slate-600">
                Type
                <select name="doc_type" class="mt-0.5 block rounded-md border border-slate-300 px-2 py-1 text-sm">
                    <option value="">All</option>
                    @include('operations.documents.partials.type-options', ['selected' => $documentTypeFilter])
                </select>
            </label>
            <button type="submit" class="inline-flex min-h-8 items-center rounded-sm border border-slate-300 px-2 text-xs font-semibold text-slate-700 hover:border-slate-500">Filter</button>
        </form>

        @include('operations.documents.partials.document-list', [
            'documents' => $customerDocuments,
            'customer' => $customer,
            'showAttachRo' => true,
            'activeRepairOrders' => $activeRepairOrders ?? collect(),
        ])
    @else
        @can(App\Ark\Runtime\Authorization\ArkCapability::CustomersManage->value)
            <div class="px-3 py-2">
                <button
                    type="button"
                    class="inline-flex min-h-9 items-center rounded-sm border border-slate-800 bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800"
                    @click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'hub-document', invokeEl: $event.currentTarget } }))"
                >
                    + Add Document
                </button>
            </div>
        @endcan
    @endif
</div>
