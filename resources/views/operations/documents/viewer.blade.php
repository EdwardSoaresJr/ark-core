@php
    $canEmail = auth()->user()?->can(\App\Ark\Runtime\Authorization\ArkCapability::CustomersManage->value)
        || auth()->user()?->can(\App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value);
@endphp

<x-operations.app :title="$document->title">
    <section class="mx-auto max-w-5xl space-y-3 px-3 py-4">
        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @error('email')
            <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ $message }}
            </div>
        @enderror

        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-3">
            <div class="min-w-0">
                <p class="ops-eyebrow">{{ $row['type_label'] }}</p>
                <h1 class="mt-0.5 text-xl font-black tracking-tight text-slate-950">{{ $document->title }}</h1>
                <p class="ops-meta mt-1">
                    {{ $customer->name }}
                    @if ($row['repair_order_number'] ?? null)
                        <span class="mx-1 text-slate-300">·</span>
                        RO #{{ $row['repair_order_number'] }}
                    @endif
                    <span class="mx-1 text-slate-300">·</span>
                    {{ ($row['customer_visible'] ?? false) ? 'Customer visible' : 'Shop only' }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <a href="{{ $row['download_url'] }}" class="inline-flex min-h-9 items-center rounded-sm border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:border-slate-500">Download</a>
                <button type="button" class="inline-flex min-h-9 items-center rounded-sm border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:border-slate-500" onclick="window.print()">Print</button>
                @if ($canEmail)
                    <a href="#document-email-{{ $document->id }}" class="inline-flex min-h-9 items-center rounded-sm border border-slate-800 bg-slate-900 px-3 text-xs font-semibold text-white hover:bg-slate-800">Email</a>
                @endif
                @if ($canManage)
                    <form method="POST" action="{{ $row['rotate_url'] }}" class="inline">
                        @csrf
                        <input type="hidden" name="direction" value="left">
                        <button type="submit" class="inline-flex min-h-9 items-center rounded-sm border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:border-slate-500">Rotate Left</button>
                    </form>
                    <form method="POST" action="{{ $row['rotate_url'] }}" class="inline">
                        @csrf
                        <input type="hidden" name="direction" value="right">
                        <button type="submit" class="inline-flex min-h-9 items-center rounded-sm border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:border-slate-500">Rotate Right</button>
                    </form>
                @endif
                <a href="{{ route('operations.customers.show', [$customer, 'tab' => 'documents']) }}" class="inline-flex min-h-9 items-center rounded-sm border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:border-slate-500">Back</a>
            </div>
        </div>

        @if ($canEmail)
            @include('operations.documents.partials.document-email-form', [
                'customer' => $customer,
                'documentId' => $document->id,
                'emailUrl' => $row['email_url'],
            ])
        @endif

        @include('operations.documents.partials.document-email-log', [
            'emailSends' => $emailSends ?? [],
        ])

        <div class="overflow-hidden rounded-sm border border-slate-200 bg-slate-100">
            @if ($row['is_image'] ?? false)
                <img src="{{ $row['url'] }}" alt="{{ $document->title }}" class="mx-auto max-h-[80vh] w-auto object-contain">
            @else
                <iframe src="{{ $row['url'] }}" title="{{ $document->title }}" class="h-[80vh] w-full bg-white"></iframe>
            @endif
        </div>
    </section>
</x-operations.app>
