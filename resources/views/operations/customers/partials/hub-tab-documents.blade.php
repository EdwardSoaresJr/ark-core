@php
    $customerDocuments = $customerDocuments ?? collect();
    $documentTypeFilter = request('doc_type');
    $documentQuery = request('doc_q');
    $hasDocuments = $customerDocuments->isNotEmpty();
@endphp

<div id="customer-documents">
    <div class="card-body border-bottom py-2 d-flex flex-wrap align-items-center gap-2">
        <div class="me-auto">
            <h3 class="card-title mb-0">Documents</h3>
            <div class="text-secondary small">Warranty contracts, registration, outside invoices, alignment sheets - paperwork for this customer.</div>
        </div>
        @can(App\Ark\Runtime\Authorization\ArkCapability::CustomersManage->value)
            <button
                type="button"
                class="btn btn-primary"
                x-on:click="window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail: { task: 'hub-document', invokeEl: $event.currentTarget } }))"
            >
                <x-tabler.icon name="plus" />
                Add Document
            </button>
        @endcan
    </div>

    @if ($hasDocuments)
        <form method="GET" action="{{ route('operations.customers.show', $customer) }}" class="card-body border-bottom py-2">
            <input type="hidden" name="tab" value="documents">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-sm">
                    <label for="customer-documents-search" class="form-label">Search</label>
                    <input id="customer-documents-search" type="search" name="doc_q" value="{{ $documentQuery }}" class="form-control" placeholder="Title">
                </div>
                <div class="col-8 col-sm-auto">
                    <label for="customer-documents-type" class="form-label">Type</label>
                    <select id="customer-documents-type" name="doc_type" class="form-select">
                        <option value="">All</option>
                        @include('operations.documents.partials.type-options', ['selected' => $documentTypeFilter])
                    </select>
                </div>
                <div class="col-4 col-sm-auto">
                    <button type="submit" class="btn w-100">Filter</button>
                </div>
            </div>
        </form>

        @include('operations.documents.partials.document-list', [
            'documents' => $customerDocuments,
            'customer' => $customer,
            'showAttachRo' => true,
            'activeRepairOrders' => $activeRepairOrders ?? collect(),
        ])
    @else
        <div class="card-body text-secondary">No documents for this customer yet.</div>
    @endif
</div>
