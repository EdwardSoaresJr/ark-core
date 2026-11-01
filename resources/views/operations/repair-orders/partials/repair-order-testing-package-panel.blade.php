@php
    use App\Ark\Operations\WorkAuthorization\WorkAuthorizationStatus;

    /** @var \Illuminate\Support\Collection<int, \App\Ark\Operations\WorkAuthorization\WorkAuthorization> $testingAuthorizations */
    $testingAuthorizations = $testingAuthorizations ?? collect();
@endphp

<section id="work-authorization" class="card mb-3 scroll-mt-6" data-testing-package-panel aria-labelledby="work-authorization-title">
    <div class="card-header">
        <div>
            <div class="subheader">Work Authorization</div>
            <h3 class="card-title" id="work-authorization-title">Testing Package</h3>
            <div class="card-subtitle small">What testing has the customer authorized? Not hours.</div>
        </div>
        @if (! ($isTerminal ?? false) && ! ($presentationOnly ?? false))
            <div class="card-actions">
                <form
                    method="POST"
                    action="{{ route('operations.repair-orders.work-authorization.testing.store', $repairOrder) }}"
                    data-refresh-scope="worksheet"
                    data-saving-label="Saving…"
                    @submit.prevent="submitWorksheetForm($event)"
                >
                    @csrf
                    <input type="hidden" name="{{ App\Ark\Operations\RepairOrders\RepairOrderConcurrency::FIELD }}" value="{{ $estimateVersion }}">
                    <button type="submit" class="btn btn-primary btn-sm">
                        + Authorize Testing Package
                    </button>
                </form>
            </div>
        @endif
    </div>

    <div class="list-group list-group-flush">
        @forelse ($testingAuthorizations as $authorization)
            <div class="list-group-item">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                    <div>
                        <div class="fw-medium">
                            {{ $authorization->package_type->label() }}
                            <span class="fw-normal text-secondary">· {{ $authorization->status->label() }}</span>
                        </div>
                        @if ($authorization->concern)
                            <div class="small text-secondary">Concern · {{ $authorization->concern->summary }}</div>
                        @endif
                        @if ($authorization->workGroup)
                            <div class="small text-secondary">Repair Action · {{ $authorization->workGroup->title }}</div>
                        @endif
                        @if ($authorization->outcome)
                            <div class="mt-1">
                                Outcome · <span class="fw-bold">{{ $authorization->outcome->label() }}</span>
                            </div>
                            @if (filled($authorization->recommendation))
                                <div class="small text-secondary mt-1">{{ $authorization->recommendation }}</div>
                            @endif
                        @endif
                    </div>
                    @if ($authorization->status->isOpen() && ! ($isTerminal ?? false))
                        <a
                            href="{{ route('operations.repair-orders.work-authorization.outcome', [$repairOrder, $authorization]) }}"
                            class="btn btn-sm"
                        >
                            Record Outcome
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="list-group-item small text-secondary">
                No Testing Package authorized on this repair order yet.
            </div>
        @endforelse
    </div>
</section>
