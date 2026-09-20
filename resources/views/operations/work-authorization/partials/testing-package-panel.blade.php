@php
    use App\Ark\Operations\WorkAuthorization\WorkAuthorizationStatus;

    /** @var \Illuminate\Support\Collection<int, \App\Ark\Operations\WorkAuthorization\WorkAuthorization> $testingAuthorizations */
    $testingAuthorizations = $testingAuthorizations ?? collect();
@endphp

<section id="work-authorization" class="mb-4 scroll-mt-6 rounded-md border border-slate-200 bg-white px-3 py-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Work Authorization</p>
            <p class="text-sm font-medium text-slate-900">Testing Package</p>
            <p class="mt-0.5 text-xs text-slate-500">What testing has the customer authorized? Not hours.</p>
        </div>
        @if (! ($isTerminal ?? false) && ! ($presentationOnly ?? false))
            <form
                method="POST"
                action="{{ route('operations.repair-orders.work-authorization.testing.store', $repairOrder) }}"
                data-refresh-scope="worksheet"
                data-saving-label="Saving…"
                @submit.prevent="submitWorksheetForm($event)"
            >
                @csrf
                <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                <button type="submit" class="rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800">
                    + Authorize Testing Package
                </button>
            </form>
        @endif
    </div>

    @forelse ($testingAuthorizations as $authorization)
        <div class="mt-3 border-t border-slate-100 pt-3 text-sm">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="font-medium text-slate-900">
                        {{ $authorization->package_type->label() }}
                        <span class="font-normal text-slate-500">· {{ $authorization->status->label() }}</span>
                    </p>
                    @if ($authorization->concern)
                        <p class="text-slate-600">Concern · {{ $authorization->concern->summary }}</p>
                    @endif
                    @if ($authorization->workGroup)
                        <p class="text-slate-600">Repair Action · {{ $authorization->workGroup->title }}</p>
                    @endif
                    @if ($authorization->outcome)
                        <p class="mt-1 text-slate-800">
                            Outcome · <span class="font-semibold">{{ $authorization->outcome->label() }}</span>
                        </p>
                        @if (filled($authorization->recommendation))
                            <p class="mt-0.5 text-slate-600">{{ $authorization->recommendation }}</p>
                        @endif
                    @endif
                </div>
                @if ($authorization->status->isOpen() && ! ($isTerminal ?? false))
                    <a
                        href="{{ route('operations.repair-orders.work-authorization.outcome', [$repairOrder, $authorization]) }}"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-800 hover:bg-slate-50"
                    >
                        Record Outcome
                    </a>
                @endif
            </div>
        </div>
    @empty
        <p class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-500">
            No Testing Package authorized on this repair order yet.
        </p>
    @endforelse
</section>
