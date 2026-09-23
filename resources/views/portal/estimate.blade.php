<x-portal.app>
    @php
        $shopPhone = $snapshot['shop']['phone_display'] ?? $snapshot['shop']['phone'] ?? null;
        $shopPhoneTel = preg_replace('/\D+/', '', (string) ($snapshot['shop']['phone'] ?? $shopPhone ?? ''));
        $concerns = \App\Ark\Operations\RepairOrders\RecommendationIntent::sortedSnapshotConcerns($snapshot['concerns'] ?? []);
        $totals = $snapshot['totals'] ?? [];
        $totalsBreakdown = \App\Ark\Operations\Financial\CustomerEstimateTotalsPresentation::fromSnapshotTotals(
            $totals,
            data_get($snapshot, 'customer.customer_type') ?? $repairOrder->customer?->customer_type,
        );
        $totalsBreakdown['total_label'] = data_get($snapshot, 'document_footer.total_label', 'Total');
        $approvalForecast = is_array($snapshot['approval_forecast'] ?? null)
            ? $snapshot['approval_forecast']
            : null;
        if (($approvalForecast['visible'] ?? false) === true) {
            $totalsBreakdown['total_label'] = 'Approved';
            $totalsBreakdown['final_emphasis'] = false;
        }
        $payingRemaining = (bool) ($payingRemaining ?? false);
        $depositAmountCents = (int) ($portalAuthorization['deposit_amount_cents'] ?? $portalAuthorization['approved_amount_cents'] ?? 0);
        $depositAmountLabel = is_array($portalAuthorization)
            ? ($portalAuthorization['deposit_amount'] ?? $portalAuthorization['approved_amount'] ?? null)
            : null;
        $showDeposit = $depositEnabled
            && is_array($portalAuthorization)
            && $depositAmountCents > 0
            && ! $depositCollected;

        $estimateStep = match (true) {
            $showDeposit => 'pay_deposit',
            is_array($portalAuthorization),
            $latestRecordedApproval && ! $canAuthorize,
            $presentedWorkIsFullyApproved && ! $canAuthorize => 'done',
            $canAuthorize => 'authorize',
            default => 'review',
        };
    @endphp

    <section @class([
        'portal-estimate-shell customer-panel customer-panel--flush overflow-hidden',
        'portal-estimate-shell--mobile-bar' => $concerns->isNotEmpty() || $canAuthorize || $showDeposit,
    ])>
        <div class="portal-estimate-hero border-b border-slate-200/80 px-4 py-6 sm:px-6 lg:px-8 lg:py-7">
            @if ($staffPreview ?? false)
                <div class="portal-estimate-staff-banner mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    <p class="font-semibold">Staff preview</p>
                    <p class="mt-1 text-amber-900/90">This view is not logged as a customer opening the estimate.</p>
                </div>
            @endif

            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#0099cc]">Repair estimate</p>
                    <h1 class="mt-1.5 text-2xl font-bold tracking-tight text-slate-950 sm:text-[1.75rem] lg:text-[2rem]">{{ $repairOrder->vehicle->display_name }}</h1>
                </div>

                @if ($estimatePdfAvailable ?? false)
                    <div class="shrink-0 sm:pt-1">
                        @include('portal.partials._estimate-pdf-actions', ['token' => $token])
                    </div>
                @endif
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700">
                    RO #{{ $repairOrder->repair_order_id }}
                </span>
                @if (filled($preparedOn))
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700">
                        Prepared {{ $preparedOn }}
                    </span>
                @endif
                @if (filled($customerStatusLabel ?? null))
                    @php
                        $statusLower = strtolower($customerStatusLabel);
                        $statusBadgeClass = str_contains($statusLower, 'await')
                            ? 'bg-amber-100 text-amber-900 ring-1 ring-amber-200/80'
                            : (str_contains($statusLower, 'approv')
                                ? 'bg-emerald-100 text-emerald-900 ring-1 ring-emerald-200/80'
                                : 'bg-slate-100 text-slate-700 ring-1 ring-slate-200/80');
                    @endphp
                    <span class="rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wide {{ $statusBadgeClass }}">
                        {{ $customerStatusLabel }}
                    </span>
                @endif
            </div>

            <div class="mt-5">
                @include('portal.partials._estimate-step-indicator', [
                    'current' => $estimateStep,
                    'depositEnabled' => $depositEnabled,
                    'payingRemaining' => $payingRemaining,
                ])
            </div>

            @if ($canAuthorize)
                @include('portal.partials._estimate-authorize-instructions', [
                    'depositEnabled' => $depositEnabled,
                    'class' => 'mt-5',
                ])
            @endif

            @if (is_array($portalAuthorization))
                @php
                    $approvedAmountLabel = $portalAuthorization['approved_amount'] ?? null;
                    $vehicleLabel = $repairOrder->vehicle->display_name;
                    $thankYouLead = filled($customerFirstName ?? null)
                        ? 'Thank you, '.$customerFirstName.' -'
                        : 'Thank you -';
                    $justSubmittedIntro = filled($approvedAmountLabel)
                        ? $thankYouLead.' we received your approval for '.$approvedAmountLabel.' in repairs for your '.$vehicleLabel.'.'
                        : $thankYouLead.' we received your approval for your '.$vehicleLabel.'.';
                @endphp
                @include('portal.partials._authorization-record', [
                    'record' => \App\Ark\Operations\Portal\PortalCustomerAuthorizationPresentation::fromSessionFlash($portalAuthorization),
                    'title' => ($authorizationFromSession ?? false) ? 'You’re all set' : 'We have your approval',
                    'intro' => ($authorizationFromSession ?? false)
                        ? $justSubmittedIntro
                        : 'Thanks - your estimate choices are already with us.',
                    'class' => 'mt-6',
                ])
                @if ($showDeposit && filled($depositAmountLabel))
                    @include('portal.partials._estimate-deposit-callout', [
                        'depositAmount' => $depositAmountLabel,
                        'payingRemaining' => $payingRemaining,
                        'class' => 'mt-4',
                    ])
                @endif
                @include('portal.partials._authorization-next-steps', [
                    'shopPhone' => $shopPhone,
                    'showDeposit' => $showDeposit,
                    'depositAmount' => $depositAmountLabel,
                    'payingRemaining' => $payingRemaining,
                ])
            @elseif ($latestRecordedApproval && ! $canAuthorize)
                @include('portal.partials._authorization-record', [
                    'record' => \App\Ark\Operations\Portal\PortalCustomerAuthorizationPresentation::fromApprovalEvent($latestRecordedApproval),
                    'title' => 'We have your approval',
                    'intro' => 'We’ve recorded the work you authorized with your advisor.',
                    'class' => 'mt-6',
                ])
                @if ($showDeposit && filled($depositAmountLabel))
                    @include('portal.partials._estimate-deposit-callout', [
                        'depositAmount' => $depositAmountLabel,
                        'payingRemaining' => $payingRemaining,
                        'class' => 'mt-4',
                    ])
                @endif
                @include('portal.partials._authorization-next-steps', [
                    'shopPhone' => $shopPhone,
                    'showDeposit' => $showDeposit,
                    'depositAmount' => $depositAmountLabel,
                    'payingRemaining' => $payingRemaining,
                ])
            @elseif ($presentedWorkIsFullyApproved && ! $canAuthorize)
                <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    <p class="font-semibold">Work approved</p>
                    <p class="mt-1">We’ve recorded the work you authorized with your advisor.</p>
                </div>
                @include('portal.partials._authorization-next-steps', [
                    'shopPhone' => $shopPhone,
                    'showDeposit' => $showDeposit,
                    'depositAmount' => $depositAmountLabel,
                    'payingRemaining' => $payingRemaining,
                ])
            @endif

            @php
                $notice = is_array($paymentNotice ?? null) ? $paymentNotice : null;
                $noticeKind = $notice['kind'] ?? null;
            @endphp
            @if ($noticeKind === 'complete')
                <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    <p class="font-semibold">{{ $notice['title'] }}</p>
                    @if (filled($notice['body'] ?? null))
                        <p class="mt-1">{{ $notice['body'] }}</p>
                    @endif
                    @if (filled($shopDisplayName ?? null))
                        <p class="mt-2">
                            {{ $shopDisplayName }} has your approval and payment. We’ll take it from here and keep you updated along the way.
                        </p>
                    @endif
                </div>
            @elseif ($noticeKind === 'partial')
                <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    <p class="font-semibold">{{ $notice['title'] }}</p>
                    @if (filled($notice['body'] ?? null))
                        <p class="mt-1">{{ $notice['body'] }}</p>
                    @endif
                    @if (filled($notice['remaining_line'] ?? null))
                        <p class="mt-2 font-semibold tabular-nums text-emerald-950">{{ $notice['remaining_line'] }}</p>
                    @endif
                </div>
            @elseif ($noticeKind === 'balance_due')
                <p class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-900">
                    {{ $notice['title'] }}
                </p>
            @elseif ($depositCollected)
                <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    <p class="font-semibold">Payment received</p>
                    <p class="mt-1">Thank you - we received your payment.</p>
                </div>
            @endif

            @if (
                filled($shopHeadline ?? null)
                && (
                    is_array($portalAuthorization)
                    || ($latestRecordedApproval && ! $canAuthorize)
                    || ($presentedWorkIsFullyApproved && ! $canAuthorize)
                    || $noticeKind === 'complete'
                    || $noticeKind === 'partial'
                )
            )
                <p class="mt-4 text-center text-xs leading-5 text-slate-500">
                    <span class="font-semibold text-slate-700">{{ $shopDisplayName }}</span>
                    <span class="mx-1.5 text-slate-300" aria-hidden="true">·</span>
                    {{ $shopHeadline }}
                </p>
            @endif
        </div>

        @if ($concerns->isEmpty())
            <div class="space-y-4 px-4 py-5 sm:px-6 lg:px-8">
                @include('portal.partials._estimate-visit-context', [
                    'snapshot' => $snapshot,
                    'repairOrder' => $repairOrder,
                ])

                <p class="text-sm text-slate-600">
                    {{ $snapshot['intake']['concern_summary'] ?? 'Estimate details are being prepared.' }}
                </p>
            </div>

            <div class="border-t border-slate-100 px-4 py-5 sm:px-6 lg:px-8">
                @include('portal.partials._estimate-summary-panel', [
                    'totalsBreakdown' => $totalsBreakdown,
                    'approvalForecast' => $approvalForecast,
                ])

                @include('partials.public.financing-note', [
                    'class' => 'mt-5',
                    'showProgramButtons' => true,
                ])
            </div>

            @if ($canAuthorize || $showDeposit)
                <div class="border-t border-slate-100 px-4 pb-6 sm:px-6 lg:px-8">
                    @if ($canAuthorize)
                        @include('portal.partials._estimate-authorization-form', [
                            'snapshot' => $snapshot,
                            'pendingConcerns' => $pendingConcerns,
                            'authorizationForm' => $authorizationForm,
                            'authorizationMode' => $authorizationMode,
                            'token' => $token,
                            'customerName' => $customerName,
                            'signatureRequired' => $signatureRequired,
                            'authorizationLanguage' => $authorizationLanguage,
                            'discountNote' => $totalsBreakdown['discount_note'] ?? null,
                            'depositEnabled' => $depositEnabled,
                        ])
                    @endif

                    @if ($showDeposit)
                        @include('portal.partials._estimate-deposit-panel', [
                            'token' => $token,
                            'portalAuthorization' => $portalAuthorization,
                            'square' => $square,
                            'staffPreview' => $staffPreview ?? false,
                            'payingRemaining' => $payingRemaining,
                        ])
                    @endif
                </div>
            @endif
        @else
            @if ($canAuthorize)
                <div
                    class="portal-estimate-authorize-shell"
                    x-data="arkPortalEstimateForm({
                        authorization: {
                            concerns: @js($authorizationForm->authorizationConcerns),
                            initialDispositions: @js($authorizationForm->initialDispositions),
                        },
                        signatureRequired: @js($signatureRequired),
                        depositEnabled: @js($depositEnabled),
                    })"
                    @if ($signatureRequired)
                        x-init="init()"
                    @endif
                >
            @endif

            <div class="customer-page-split customer-page-split--portal px-4 py-6 sm:px-6 lg:px-8 lg:py-7">
                <div class="customer-page-split__primary space-y-6">
                    @if ($showDeposit && ! $canAuthorize)
                        @include('portal.partials._estimate-deposit-panel', [
                            'token' => $token,
                            'portalAuthorization' => $portalAuthorization,
                            'square' => $square,
                            'staffPreview' => $staffPreview ?? false,
                            'payingRemaining' => $payingRemaining,
                        ])
                    @endif

                    @include('portal.partials._estimate-visit-context', [
                        'snapshot' => $snapshot,
                        'repairOrder' => $repairOrder,
                    ])

                    <div>
                        <div class="flex items-end justify-between gap-3">
                            <h2 class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Recommended Work</h2>
                            <span class="text-xs font-semibold tabular-nums text-slate-500">{{ $concerns->count() }} {{ $concerns->count() === 1 ? 'service' : 'services' }}</span>
                        </div>
                        <div class="mt-4 space-y-3">
                            @foreach ($concerns as $index => $concern)
                                @include('portal.partials._estimate-concern-card', [
                                    'concern' => $concern,
                                    'snapshot' => $snapshot,
                                    'serviceNumber' => $index + 1,
                                    'suppressPendingLabel' => $canAuthorize,
                                    'evidenceByConcern' => $evidenceByConcern ?? [],
                                ])
                            @endforeach
                        </div>

                        @if (($generalEvidence ?? collect())->isNotEmpty())
                            <div class="mt-6">
                                <h3 class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Additional evidence</h3>
                                @include('operations.evidence.partials.evidence-items', ['items' => $generalEvidence])
                            </div>
                        @endif
                    </div>

                    @if ($canAuthorize)
                        <div class="portal-estimate-summary-mobile md:hidden">
                            <div class="customer-panel portal-estimate-summary-wrap w-full">
                                @include('portal.partials._estimate-summary-panel', [
                                    'totalsBreakdown' => $totalsBreakdown,
                                    'approvalForecast' => $approvalForecast,
                                ])
                            </div>
                            @include('partials.public.financing-note', [
                                'class' => 'mt-4',
                                'showProgramButtons' => true,
                            ])
                        </div>
                    @endif

                    @if ($canAuthorize)
                        <div id="portal-estimate-authorize">
                            @include('portal.partials._estimate-authorization-form', [
                                'snapshot' => $snapshot,
                                'pendingConcerns' => $pendingConcerns,
                                'authorizationForm' => $authorizationForm,
                                'authorizationMode' => $authorizationMode,
                                'token' => $token,
                                'customerName' => $customerName,
                                'signatureRequired' => $signatureRequired,
                                'authorizationLanguage' => $authorizationLanguage,
                                'discountNote' => $totalsBreakdown['discount_note'] ?? null,
                                'alpineScope' => 'root',
                                'depositEnabled' => $depositEnabled,
                            ])
                        </div>
                    @endif
                </div>

                <aside @class([
                    'customer-page-split__rail min-w-0 space-y-6 md:self-start',
                    'hidden md:block' => $canAuthorize,
                ])>
                    <div class="customer-panel customer-panel--sticky portal-estimate-summary-wrap">
                        @include('portal.partials._estimate-summary-panel', [
                            'totalsBreakdown' => $totalsBreakdown,
                            'approvalForecast' => $approvalForecast,
                        ])
                    </div>

                    @include('partials.public.financing-note', [
                        'showProgramButtons' => true,
                    ])
                </aside>
            </div>

            @if ($concerns->isNotEmpty() || $canAuthorize || $showDeposit)
                <div class="portal-estimate-mobile-bar lg:hidden" aria-label="{{ $showDeposit ? ($payingRemaining ? 'Pay remaining' : 'Pay deposit') : ($canAuthorize ? 'Approved total' : 'Total') }}">
                    <div class="portal-estimate-mobile-bar__inner flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            @if ($showDeposit && filled($depositAmountLabel))
                                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-amber-700">{{ $payingRemaining ? 'Remaining balance' : 'Deposit due' }}</p>
                                <p class="text-xl font-black tabular-nums tracking-tight text-slate-950">{{ $depositAmountLabel }}</p>
                            @elseif ($canAuthorize)
                                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">Approved total</p>
                                <p class="text-xl font-black tabular-nums tracking-tight text-slate-950" x-text="approvedTotalLabel()"></p>
                            @else
                                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">{{ $totalsBreakdown['total_label'] ?? 'Total' }}</p>
                                <p class="text-xl font-black tabular-nums tracking-tight text-slate-950">{{ $totalsBreakdown['total'] ?? '-' }}</p>
                            @endif
                        </div>
                        @if ($showDeposit && filled($depositAmountLabel))
                            <a
                                href="#portal-estimate-deposit"
                                class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-amber-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-amber-700"
                            >
                                Pay now
                            </a>
                        @elseif ($canAuthorize)
                            <a
                                href="#portal-estimate-authorize"
                                class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-[#0099cc] px-5 text-sm font-semibold text-white shadow-sm hover:bg-[#007aa3]"
                            >
                                Approve
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            @if ($canAuthorize)
                </div>
            @endif
        @endif

        <div class="portal-estimate-footer-band border-t border-slate-100 px-4 py-5 sm:px-6 lg:px-8">
            @include('portal.partials._estimate-important-information', [
                'footer' => $snapshot['document_footer'] ?? [],
            ])

            @include('portal.partials.vehicle-records-link', [
                'vehicleRecordsLink' => $vehicleRecordsLink ?? null,
                'vehicleName' => $repairOrder->vehicle->display_name,
            ])

            @include('portal.partials._shop-contact-card', [
                'shopPhone' => $shopPhone,
                'shopPhoneTel' => $shopPhoneTel,
                'class' => 'mt-4',
            ])
        </div>
    </section>
</x-portal.app>
