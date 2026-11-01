@php
    $canEmail = auth()->user()?->can(\App\Ark\Runtime\Authorization\ArkCapability::CustomersManage->value)
        || auth()->user()?->can(\App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value);
@endphp

<x-operations.app :title="$document->title" ui="tabler">
    <x-tabler.page layout="focused" :title="$document->title" :pretitle="$row['type_label']" actions-label="Document commands" stack-actions>
        <x-slot:actions>
            <x-tabler.page-action :href="route('operations.customers.show', [$customer, 'tab' => 'documents'])" icon="arrow-left" label="Back" />
            <x-tabler.page-action :href="$row['download_url']" icon="arrow-down" label="Download" />
            <x-tabler.page-action icon="printer" label="Print" onclick="window.print()" />
            @if ($canEmail)
                <x-tabler.page-action :href="'#document-email-'.$document->id" icon="mail" label="Email" primary />
            @endif
        </x-slot:actions>

        @if (session('status'))
            <div class="alert alert-success d-print-none" role="status">{{ session('status') }}</div>
        @endif

        @error('email')
            <div class="alert alert-danger d-print-none" role="alert">{{ $message }}</div>
        @enderror

        <div class="text-secondary mb-3" data-document-viewer-meta>
            {{ $customer->name }}
            @if ($row['repair_order_number'] ?? null)
                · RO #{{ $row['repair_order_number'] }}
            @endif
            · {{ ($row['customer_visible'] ?? false) ? 'Customer visible' : 'Shop only' }}
        </div>

        @if ($canEmail)
            <div class="mb-3 d-print-none">
                @include('operations.documents.partials.document-email-form', [
                    'customer' => $customer,
                    'documentId' => $document->id,
                    'emailUrl' => $row['email_url'],
                    'ui' => 'tabler',
                ])
            </div>
        @endif

        @if (($emailSends ?? []) !== [])
            <div class="mb-3">
                @include('operations.documents.partials.document-email-log', [
                    'emailSends' => $emailSends ?? [],
                ])
            </div>
        @endif

        <div class="card" data-document-viewer-preview>
            @if ($canManage)
                <div class="card-header d-print-none">
                    <h3 class="card-title">Preview</h3>
                    <div class="card-actions btn-list">
                        <form method="POST" action="{{ $row['rotate_url'] }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="direction" value="left">
                            <button type="submit" class="btn btn-sm">Rotate Left</button>
                        </form>
                        <form method="POST" action="{{ $row['rotate_url'] }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="direction" value="right">
                            <button type="submit" class="btn btn-sm">Rotate Right</button>
                        </form>
                    </div>
                </div>
            @endif
            @if ($row['is_image'] ?? false)
                <img src="{{ $row['url'] }}" alt="{{ $document->title }}" class="d-block mx-auto img-fluid" style="max-height: 80vh">
            @else
                <iframe src="{{ $row['url'] }}" title="{{ $document->title }}" class="d-block w-100 border-0" style="height: 80vh"></iframe>
            @endif
        </div>
    </x-tabler.page>
</x-operations.app>
