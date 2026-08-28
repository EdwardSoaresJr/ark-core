{{--
  Email paperwork form — viewer, Hub list expand, or RO modal.
  Props: $customer, $documentId (or $doc with id), $emailUrl optional, $compact optional
--}}
@php
    use App\Ark\Runtime\Authorization\ArkCapability;

    $canEmail = auth()->user()?->can(ArkCapability::CustomersManage->value)
        || auth()->user()?->can(ArkCapability::RepairOrdersManage->value);
    $documentId = $documentId ?? ($doc['id'] ?? $document->id ?? null);
    $emailUrl = $emailUrl ?? ($doc['email_url'] ?? null);
    if ($emailUrl === null && $documentId !== null) {
        $emailUrl = route('operations.customers.documents.email', [$customer, $documentId]);
    }
    $compact = (bool) ($compact ?? false);
    $customerEmail = trim((string) ($customer->email ?? ''));
    $formId = 'document-email-'.$documentId;
@endphp

@if ($canEmail && $emailUrl)
    <div
        id="{{ $formId }}"
        @class([
            'rounded-sm border border-slate-200 bg-white',
            'p-3' => ! $compact,
            'p-2' => $compact,
        ])
    >
        <form method="POST" action="{{ $emailUrl }}" class="grid gap-2">
            @csrf
            <div>
                <p @class([
                    'font-bold uppercase tracking-[0.08em] text-slate-400',
                    'text-[11px]' => ! $compact,
                    'text-[10px]' => $compact,
                ])>Email document</p>
                @unless ($compact)
                    <p class="mt-0.5 text-xs leading-4 text-slate-500">Sends this paperwork file to the customer.</p>
                @endunless
            </div>
            <label class="block">
                <span class="sr-only">Customer email</span>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email', $customerEmail) }}"
                    placeholder="Customer email"
                    required
                    @class([
                        'w-full rounded-sm border-slate-300 text-slate-700',
                        'h-9 text-sm' => ! $compact,
                        'h-8 text-xs' => $compact,
                    ])
                >
            </label>
            <label class="block">
                <span class="sr-only">Optional note</span>
                <input
                    type="text"
                    name="message"
                    value="{{ old('message') }}"
                    placeholder="Optional note"
                    maxlength="500"
                    @class([
                        'w-full rounded-sm border-slate-300 text-slate-700',
                        'h-9 text-sm' => ! $compact,
                        'h-8 text-xs' => $compact,
                    ])
                >
            </label>
            <button
                type="submit"
                @class([
                    'inline-flex items-center justify-center rounded-sm border border-slate-800 bg-slate-900 font-semibold text-white hover:bg-slate-800',
                    'min-h-9 px-3 text-xs' => ! $compact,
                    'min-h-8 px-2 text-[11px]' => $compact,
                ])
            >
                Email
            </button>
        </form>
    </div>
@endif
