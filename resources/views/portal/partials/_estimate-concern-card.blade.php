@php
    $lineCount = collect($concern['lines'] ?? [])
        ->merge(collect($concern['work_groups'] ?? [])->flatMap(fn ($group): array => $group['lines'] ?? []))
        ->filter(fn ($line): bool => is_array($line))
        ->count();

    $disposition = $concern['disposition'] ?? 'draft';
    $showDispositionLabel = filled($concern['disposition_label'] ?? null)
        && $disposition !== 'draft'
        && ! (($suppressPendingLabel ?? false) && $disposition === 'recommended');

    $intent = \App\Ark\Operations\RepairOrders\RecommendationIntent::fromStored(
        (string) ($concern['recommendation_intent'] ?? ''),
    );
    $priorityLabel = match ($intent) {
        \App\Ark\Operations\RepairOrders\RecommendationIntent::Diagnostic => 'Needs more testing',
        \App\Ark\Operations\RepairOrders\RecommendationIntent::RepairVerification => 'Checking a repair',
        \App\Ark\Operations\RepairOrders\RecommendationIntent::InformationOnly => 'For your information',
        default => $intent->customerLabel(),
    };

    $accentClass = match ($disposition) {
        'approved' => 'border-l-emerald-500',
        'deferred' => 'border-l-amber-500',
        'declined' => 'border-l-rose-500',
        'recommended' => 'border-l-[#0099cc]',
        default => 'border-l-slate-300',
    };
@endphp

<article
    class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm {{ $accentClass }} border-l-4"
    x-data="{ showDetails: false }"
>
    <div class="px-4 py-4 sm:px-5">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-bold uppercase tracking-[0.12em] {{ $intent->intentLabelClass() }}">{{ $priorityLabel }}</p>
                <p class="mt-1 text-[15px] font-semibold leading-snug text-slate-950 sm:text-base">{{ $concern['summary'] }}</p>

                @if ($showDispositionLabel)
                    <p @class([
                        'mt-1.5 inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                        'bg-emerald-50 text-emerald-800' => $disposition === 'approved',
                        'bg-amber-50 text-amber-900' => $disposition === 'deferred',
                        'bg-rose-50 text-rose-800' => $disposition === 'declined',
                        'bg-sky-50 text-sky-900' => $disposition === 'recommended',
                        'bg-slate-100 text-slate-600' => ! in_array($disposition, ['approved', 'deferred', 'declined', 'recommended'], true),
                    ])>{{ $concern['disposition_label'] }}</p>
                @endif
            </div>
            <p class="shrink-0 text-base font-black tabular-nums text-slate-950">{{ $concern['subtotal'] ?? '' }}</p>
        </div>

        @php
            $concernEvidence = collect($evidenceByConcern[(int) ($concern['id'] ?? 0)] ?? []);
            $primaryEvidence = $concernEvidence->firstWhere('is_primary', true) ?? $concernEvidence->first();
        @endphp

        @php
            $visitReason = trim((string) ($snapshot['intake']['visit_reason'] ?? ''));
            $customerStates = trim((string) ($concern['customer_states'] ?? ''));
            $findings = trim((string) ($concern['verified_findings'] ?? ''));
            $dtcs = trim((string) ($concern['dtcs_summary'] ?? ''));
            $recommendation = trim((string) ($concern['recommendation'] ?? ''));
            $duplicateCustomerStates = $customerStates !== ''
                && in_array(mb_strtolower($customerStates), array_values(array_filter([
                    mb_strtolower(trim((string) ($concern['summary'] ?? ''))),
                    mb_strtolower($visitReason),
                ])), true);
        @endphp

        @if (($customerStates !== '' && ! $duplicateCustomerStates) || $findings !== '' || $dtcs !== '' || $recommendation !== '')
            <div class="mt-3 space-y-3 text-sm leading-6 text-slate-700">
                @if ($customerStates !== '' && ! $duplicateCustomerStates)
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">You told us</p>
                        <p class="mt-1 whitespace-pre-line">{{ $customerStates }}</p>
                    </div>
                @endif
                @if ($findings !== '' || $dtcs !== '')
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">What we found</p>
                        @if ($findings !== '')
                            <p class="mt-1 whitespace-pre-line">{{ $findings }}</p>
                        @endif
                        @if ($dtcs !== '')
                            <p class="mt-1 text-slate-600">Codes: {{ $dtcs }}</p>
                        @endif
                    </div>
                @endif
                @if ($recommendation !== '')
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">Recommendation</p>
                        <p class="mt-1 whitespace-pre-line">{{ $recommendation }}</p>
                    </div>
                @endif
            </div>
        @endif

        @if ($primaryEvidence)
            <a href="{{ $primaryEvidence['url'] }}" target="_blank" rel="noopener" class="mt-3 block overflow-hidden rounded-lg border border-slate-200">
                @if ($primaryEvidence['is_image'] ?? false)
                    <img src="{{ $primaryEvidence['url'] }}" alt="{{ $primaryEvidence['caption'] ?? 'Evidence' }}" class="max-h-40 w-full object-cover" loading="lazy">
                @else
                    <span class="flex h-24 items-center justify-center bg-slate-100 text-xs font-semibold text-slate-600">{{ $primaryEvidence['type_label'] ?? 'Evidence' }}</span>
                @endif
            </a>
        @endif

        @if ($concernEvidence->count() > 1)
            @include('operations.evidence.partials.evidence-items', ['items' => $concernEvidence->reject(fn ($row) => ($primaryEvidence['id'] ?? null) === ($row['id'] ?? null))->values()])
        @endif

        @if ($lineCount > 0)
            <button
                type="button"
                class="mt-3 inline-flex min-h-10 items-center gap-1.5 text-sm font-semibold text-[#0099cc] hover:text-[#007aa3]"
                @click="showDetails = ! showDetails"
                :aria-expanded="showDetails.toString()"
            >
                <span x-show="! showDetails">Price details ({{ $lineCount }})</span>
                <span x-show="showDetails" x-cloak>Hide details</span>
                <span aria-hidden="true" class="text-xs" x-text="showDetails ? '▴' : '▾'"></span>
            </button>
        @endif
    </div>

    @if ($lineCount > 0)
        <div x-show="showDetails" x-cloak class="border-t border-slate-100 bg-slate-50/50">
            @include('portal.partials._estimate-concern-lines', ['concern' => $concern])
        </div>
    @endif
</article>
