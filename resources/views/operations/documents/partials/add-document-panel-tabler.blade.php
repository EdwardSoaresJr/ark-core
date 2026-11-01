{{--
  Add Document - Scan or Upload, in Tabler markup for x-tabler.workspace-modal (Customer Hub).
  The repair order workspace modal keeps add-document-panel. Both submit the same forms.
  Props: $customer, $storeUrl, $scanUrl, $repairOrder?, $attachableDocuments?, $attachUrl?, $customerRepairOrders?
--}}
@php
    $repairOrder = $repairOrder ?? null;
    $attachableDocuments = $attachableDocuments ?? collect();
    $attachUrl = $attachUrl ?? null;
    $defaultTitle = old('title', '');
    $initialMode = old('_document_mode');
    $attachablePayload = $attachableDocuments->map(fn ($row) => [
        'id' => $row['id'],
        'title' => $row['title'],
        'type' => $row['type'] ?? '',
        'type_label' => $row['type_label'] ?? '',
    ])->values();
@endphp

<div
    data-add-document-panel
    x-data="arkDocumentAdd({
        initialMode: @js($initialMode),
        attachable: @js($attachablePayload),
    })"
>
    <div x-show="! mode">
        <div class="list-group">
            <button type="button" class="list-group-item list-group-item-action" @click="choose('scan')">
                <div class="fw-bold">Scan Document</div>
                <div class="text-secondary small">Capture pages with the camera into one PDF.</div>
            </button>
            <button type="button" class="list-group-item list-group-item-action" @click="choose('upload')">
                <div class="fw-bold">Upload PDF / File</div>
                <div class="text-secondary small">Add a downloaded or scanned PDF, JPG, or PNG.</div>
            </button>
            @if ($attachUrl && $attachableDocuments->isNotEmpty())
                <button type="button" class="list-group-item list-group-item-action" @click="choose('attach')">
                    <div class="fw-bold">Attach Existing Document</div>
                    <div class="text-secondary small">Search warranty, registration, alignment, invoice - same file, no duplicate.</div>
                </button>
            @endif
        </div>
    </div>

    <div x-show="mode === 'upload'" x-cloak>
        <button type="button" class="btn btn-link px-0 mb-2" @click="back()">
            <x-tabler.icon name="arrow-left" />
            Back
        </button>
        <form method="POST" action="{{ $storeUrl }}" enctype="multipart/form-data" data-workspace-modal-form="document-upload">
            @csrf
            <input type="hidden" name="_document_mode" value="upload">
            @if ($repairOrder)
                <input type="hidden" name="return_to_ro" value="1">
            @endif
            <div class="mb-3">
                <label class="form-label" for="document-file">File</label>
                <input id="document-file" name="file" type="file" required accept="application/pdf,image/jpeg,image/png,image/heic,image/heif,.pdf,.jpg,.jpeg,.png,.heic" class="form-control">
                <div class="form-hint">PDF, JPG, PNG, or HEIC · max 25 MB</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="document-title">Document name</label>
                <input id="document-title" name="title" type="text" required maxlength="255" value="{{ $defaultTitle }}" class="form-control" placeholder="ASC Vehicle Protection Plan">
            </div>
            <div class="mb-3">
                <label class="form-label" for="document-type">Type</label>
                <select id="document-type" name="type" required class="form-select">
                    @include('operations.documents.partials.type-options', ['selected' => old('type', 'general')])
                </select>
            </div>
            @unless ($repairOrder)
                <div class="mb-3">
                    <label class="form-label" for="document-ro">Attach to repair order (optional)</label>
                    <select id="document-ro" name="repair_order_id" class="form-select">
                        <option value="">Customer only</option>
                        @foreach (($customerRepairOrders ?? collect()) as $roOption)
                            <option value="{{ $roOption->id }}" @selected((string) old('repair_order_id') === (string) $roOption->id)>
                                RO #{{ $roOption->repair_order_id }} · {{ $roOption->status->label() ?? $roOption->status->value }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <button type="submit" class="btn btn-primary">Save Document</button>
        </form>
    </div>

    <div x-show="mode === 'scan'" x-cloak>
        <button type="button" class="btn btn-link px-0 mb-2" @click="back()">
            <x-tabler.icon name="arrow-left" />
            Back
        </button>
        <form method="POST" action="{{ $scanUrl }}" enctype="multipart/form-data" data-workspace-modal-form="document-scan" @submit="prepareScanSubmit($event)">
            @csrf
            <input type="hidden" name="_document_mode" value="scan">
            @if ($repairOrder)
                <input type="hidden" name="return_to_ro" value="1">
            @endif
            <div class="card card-sm mb-3">
                <div class="card-body">
                    <div class="btn-list">
                        <label class="btn btn-outline-primary mb-0">
                            Capture Page
                            <input type="file" accept="image/*" capture="environment" class="visually-hidden" @change="addFiles($event.target.files); $event.target.value = ''">
                        </label>
                        <label class="btn mb-0">
                            Add from Photos
                            <input type="file" accept="image/*" multiple class="visually-hidden" @change="addFiles($event.target.files); $event.target.value = ''">
                        </label>
                    </div>
                    <div class="form-hint mt-2">Pages become one PDF when you save.</div>
                </div>
            </div>
            <ul class="list-group mb-3" x-show="pages.length > 0">
                <template x-for="(page, index) in pages" :key="page.id">
                    <li class="list-group-item d-flex align-items-center gap-2">
                        <img :src="page.url" alt="" class="rounded object-fit-cover" style="width: 2.75rem; height: 3.5rem">
                        <div class="flex-fill">
                            <div class="fw-medium" x-text="'Page ' + (index + 1)"></div>
                            <div class="btn-list mt-1">
                                <button type="button" class="btn btn-sm btn-ghost-secondary" @click="movePage(page.id, -1)">Up</button>
                                <button type="button" class="btn btn-sm btn-ghost-secondary" @click="movePage(page.id, 1)">Down</button>
                                <button type="button" class="btn btn-sm btn-ghost-danger" @click="removePage(page.id)">Remove</button>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
            <div class="mb-3">
                <label class="form-label" for="document-scan-title">Document name</label>
                <input id="document-scan-title" name="title" type="text" required maxlength="255" value="{{ $defaultTitle }}" class="form-control" placeholder="Warranty contract">
            </div>
            <div class="mb-3">
                <label class="form-label" for="document-scan-type">Type</label>
                <select id="document-scan-type" name="type" required class="form-select">
                    @include('operations.documents.partials.type-options', ['selected' => old('type', 'warranty')])
                </select>
            </div>
            @unless ($repairOrder)
                <div class="mb-3">
                    <label class="form-label" for="document-scan-ro">Attach to repair order (optional)</label>
                    <select id="document-scan-ro" name="repair_order_id" class="form-select">
                        <option value="">Customer only</option>
                        @foreach (($customerRepairOrders ?? collect()) as $roOption)
                            <option value="{{ $roOption->id }}">RO #{{ $roOption->repair_order_id }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <button type="submit" class="btn btn-primary" :disabled="pages.length === 0">Save Document</button>
        </form>
    </div>

    @if ($attachUrl && $attachableDocuments->isNotEmpty())
        <div x-show="mode === 'attach'" x-cloak>
            <button type="button" class="btn btn-link px-0 mb-2" @click="back()">
                <x-tabler.icon name="arrow-left" />
                Back
            </button>
            <div class="mb-3">
                <label class="form-label" for="document-attach-search">Search</label>
                <input
                    id="document-attach-search"
                    type="search"
                    x-model="attachQuery"
                    placeholder="Warranty, registration, alignment, invoice…"
                    class="form-control"
                >
            </div>
            <form method="POST" action="{{ $attachUrl }}" data-workspace-modal-form="document-attach">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="document-attach-id">Customer document</label>
                    <select id="document-attach-id" name="document_id" required class="form-select">
                        <template x-for="row in filteredAttachable()" :key="row.id">
                            <option :value="row.id" x-text="row.title + ' · ' + row.type_label"></option>
                        </template>
                    </select>
                    <div class="form-hint" x-show="filteredAttachable().length === 0">No matching documents.</div>
                </div>
                <button type="submit" class="btn btn-primary" :disabled="filteredAttachable().length === 0">
                    Attach to this RO
                </button>
            </form>
        </div>
    @endif
</div>
