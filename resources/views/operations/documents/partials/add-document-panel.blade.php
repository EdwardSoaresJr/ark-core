{{--
  Add Document — Scan or Upload. Hosted in Workspace Modal (RO or Customer Hub).
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
    class="space-y-4"
    x-data="arkDocumentAdd({
        initialMode: @js($initialMode),
        attachable: @js($attachablePayload),
    })"
>
    <div x-show="! mode" class="grid gap-2 sm:grid-cols-2">
        <button
            type="button"
            class="rounded-sm border border-slate-300 px-3 py-3 text-left hover:border-slate-500 hover:bg-slate-50"
            @click="choose('scan')"
        >
            <span class="block text-sm font-semibold text-slate-950">Scan Document</span>
            <span class="mt-1 block text-xs text-slate-600">Capture pages with the camera into one PDF.</span>
        </button>
        <button
            type="button"
            class="rounded-sm border border-slate-300 px-3 py-3 text-left hover:border-slate-500 hover:bg-slate-50"
            @click="choose('upload')"
        >
            <span class="block text-sm font-semibold text-slate-950">Upload PDF / File</span>
            <span class="mt-1 block text-xs text-slate-600">Add a downloaded or scanned PDF, JPG, or PNG.</span>
        </button>
        @if ($attachUrl && $attachableDocuments->isNotEmpty())
            <button
                type="button"
                class="rounded-sm border border-slate-300 px-3 py-3 text-left hover:border-slate-500 hover:bg-slate-50 sm:col-span-2"
                @click="choose('attach')"
            >
                <span class="block text-sm font-semibold text-slate-950">Attach Existing Document</span>
                <span class="mt-1 block text-xs text-slate-600">Search warranty, registration, alignment, invoice — same file, no duplicate.</span>
            </button>
        @endif
    </div>

    <div x-show="mode === 'upload'" x-cloak class="space-y-3">
        <button type="button" class="text-xs font-semibold text-slate-600 underline-offset-2 hover:underline" @click="back()">← Back</button>
        <form method="POST" action="{{ $storeUrl }}" enctype="multipart/form-data" data-workspace-modal-form="document-upload" class="space-y-3">
            @csrf
            <input type="hidden" name="_document_mode" value="upload">
            @if ($repairOrder)
                <input type="hidden" name="return_to_ro" value="1">
            @endif
            <div>
                <label class="block text-[11px] font-medium text-slate-600" for="document-file">File</label>
                <input id="document-file" name="file" type="file" required accept="application/pdf,image/jpeg,image/png,image/heic,image/heif,.pdf,.jpg,.jpeg,.png,.heic" class="mt-0.5 w-full text-sm">
                <p class="mt-1 text-[11px] text-slate-500">PDF, JPG, PNG, or HEIC · max 25 MB</p>
            </div>
            <div>
                <label class="block text-[11px] font-medium text-slate-600" for="document-title">Document name</label>
                <input id="document-title" name="title" type="text" required maxlength="255" value="{{ $defaultTitle }}" class="mt-0.5 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" placeholder="ASC Vehicle Protection Plan">
            </div>
            <div>
                <label class="block text-[11px] font-medium text-slate-600" for="document-type">Type</label>
                <select id="document-type" name="type" required class="mt-0.5 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                    @include('operations.documents.partials.type-options', ['selected' => old('type', 'general')])
                </select>
            </div>
            @unless ($repairOrder)
                <div>
                    <label class="block text-[11px] font-medium text-slate-600" for="document-ro">Attach to repair order (optional)</label>
                    <select id="document-ro" name="repair_order_id" class="mt-0.5 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                        <option value="">Customer only</option>
                        @foreach (($customerRepairOrders ?? collect()) as $roOption)
                            <option value="{{ $roOption->id }}" @selected((string) old('repair_order_id') === (string) $roOption->id)>
                                RO #{{ $roOption->repair_order_id }} · {{ $roOption->status->label() ?? $roOption->status->value }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <button type="submit" class="inline-flex min-h-9 items-center rounded-sm bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800">Save Document</button>
        </form>
    </div>

    <div x-show="mode === 'scan'" x-cloak class="space-y-3">
        <button type="button" class="text-xs font-semibold text-slate-600 underline-offset-2 hover:underline" @click="back()">← Back</button>
        <form method="POST" action="{{ $scanUrl }}" enctype="multipart/form-data" data-workspace-modal-form="document-scan" class="space-y-3" @submit="prepareScanSubmit($event)">
            @csrf
            <input type="hidden" name="_document_mode" value="scan">
            @if ($repairOrder)
                <input type="hidden" name="return_to_ro" value="1">
            @endif
            <div class="rounded-sm border border-dashed border-slate-300 bg-slate-50 px-3 py-3">
                <label class="inline-flex min-h-10 cursor-pointer items-center justify-center rounded-sm border border-slate-800 bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800">
                    Capture Page
                    <input type="file" accept="image/*" capture="environment" class="sr-only" @change="addFiles($event.target.files); $event.target.value = ''">
                </label>
                <label class="ml-2 inline-flex min-h-10 cursor-pointer items-center justify-center rounded-sm border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:border-slate-500">
                    Add from Photos
                    <input type="file" accept="image/*" multiple class="sr-only" @change="addFiles($event.target.files); $event.target.value = ''">
                </label>
                <p class="mt-2 text-[11px] text-slate-500">Pages become one PDF when you save.</p>
            </div>
            <ul class="space-y-2" x-show="pages.length > 0">
                <template x-for="(page, index) in pages" :key="page.id">
                    <li class="flex items-center gap-2 rounded-sm border border-slate-200 bg-white px-2 py-2">
                        <img :src="page.url" alt="" class="h-14 w-11 rounded-sm object-cover">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-900" x-text="'Page ' + (index + 1)"></p>
                            <div class="mt-1 flex flex-wrap gap-2">
                                <button type="button" class="text-[11px] font-semibold text-slate-600 underline-offset-2 hover:underline" @click="movePage(page.id, -1)">Up</button>
                                <button type="button" class="text-[11px] font-semibold text-slate-600 underline-offset-2 hover:underline" @click="movePage(page.id, 1)">Down</button>
                                <button type="button" class="text-[11px] font-semibold text-rose-700 underline-offset-2 hover:underline" @click="removePage(page.id)">Remove</button>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
            <div>
                <label class="block text-[11px] font-medium text-slate-600" for="document-scan-title">Document name</label>
                <input id="document-scan-title" name="title" type="text" required maxlength="255" value="{{ $defaultTitle }}" class="mt-0.5 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm" placeholder="Warranty contract">
            </div>
            <div>
                <label class="block text-[11px] font-medium text-slate-600" for="document-scan-type">Type</label>
                <select id="document-scan-type" name="type" required class="mt-0.5 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                    @include('operations.documents.partials.type-options', ['selected' => old('type', 'warranty')])
                </select>
            </div>
            @unless ($repairOrder)
                <div>
                    <label class="block text-[11px] font-medium text-slate-600" for="document-scan-ro">Attach to repair order (optional)</label>
                    <select id="document-scan-ro" name="repair_order_id" class="mt-0.5 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                        <option value="">Customer only</option>
                        @foreach (($customerRepairOrders ?? collect()) as $roOption)
                            <option value="{{ $roOption->id }}">RO #{{ $roOption->repair_order_id }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <button type="submit" class="inline-flex min-h-9 items-center rounded-sm bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800" :disabled="pages.length === 0">Save Document</button>
        </form>
    </div>

    @if ($attachUrl && $attachableDocuments->isNotEmpty())
        <div x-show="mode === 'attach'" x-cloak class="space-y-3">
            <button type="button" class="text-xs font-semibold text-slate-600 underline-offset-2 hover:underline" @click="back()">← Back</button>
            <label class="block text-[11px] font-medium text-slate-600">
                Search
                <input
                    type="search"
                    x-model="attachQuery"
                    placeholder="Warranty, registration, alignment, invoice…"
                    class="mt-0.5 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                >
            </label>
            <form method="POST" action="{{ $attachUrl }}" data-workspace-modal-form="document-attach" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-[11px] font-medium text-slate-600" for="document-attach-id">Customer document</label>
                    <select id="document-attach-id" name="document_id" required class="mt-0.5 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                        <template x-for="row in filteredAttachable()" :key="row.id">
                            <option :value="row.id" x-text="row.title + ' · ' + row.type_label"></option>
                        </template>
                    </select>
                    <p class="mt-1 text-[11px] text-slate-500" x-show="filteredAttachable().length === 0">No matching documents.</p>
                </div>
                <button type="submit" class="inline-flex min-h-9 items-center rounded-sm bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800" :disabled="filteredAttachable().length === 0">
                    Attach to this RO
                </button>
            </form>
        </div>
    @endif
</div>
