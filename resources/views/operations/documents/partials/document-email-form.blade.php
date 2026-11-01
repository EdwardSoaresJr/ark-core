{{--
  Email paperwork form - viewer, Hub list expand, or RO modal.
  Props: $customer, $documentId (or $doc with id), $emailUrl optional, $compact optional,
  $ui optional ('tabler' on Tabler pages; the repair order workspace modal keeps the legacy markup)
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
    $tabler = ($ui ?? null) === 'tabler';
@endphp

@if ($canEmail && $emailUrl && $tabler)
    <div id="{{ $formId }}" @class(['card', 'card-sm' => $compact])>
        <form method="POST" action="{{ $emailUrl }}" class="card-body">
            @csrf
            @if ($compact)
                <div class="subheader mb-2">Email document</div>
            @else
                <h3 class="card-title mb-1">Email document</h3>
                <p class="text-secondary small">Sends this paperwork file to the customer.</p>
            @endif
            <div class="row g-2">
                <div class="col-12 col-md">
                    <label class="d-block mb-0">
                        <span class="visually-hidden">Customer email</span>
                        <input
                            type="email"
                            name="email"
                            value="{{ old('email', $customerEmail) }}"
                            placeholder="Customer email"
                            required
                            @class(['form-control', 'form-control-sm' => $compact])
                        >
                    </label>
                </div>
                <div class="col-12 col-md">
                    <label class="d-block mb-0">
                        <span class="visually-hidden">Optional note</span>
                        <input
                            type="text"
                            name="message"
                            value="{{ old('message') }}"
                            placeholder="Optional note"
                            maxlength="500"
                            @class(['form-control', 'form-control-sm' => $compact])
                        >
                    </label>
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" @class(['btn btn-primary w-100', 'btn-sm' => $compact])>
                        <x-tabler.icon name="mail" />
                        Email
                    </button>
                </div>
            </div>
        </form>
    </div>
@elseif ($canEmail && $emailUrl)
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
