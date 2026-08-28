@php
    use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
    use App\Ark\Operations\Messaging\RepairOrderConversationSendProjection;

    $sendProjection = app(RepairOrderConversationSendProjection::class)->forRepairOrder($repairOrder, auth()->user());
    $inspectionSend = $sendProjection['inspection'];

    $findingCount = InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder);
    $canShare = ! $isTerminal && $findingCount > 0;
    $portalLinkUrl = route('operations.repair-orders.inspection-portal-link', $repairOrder);
    $sendPortalUrl = route('operations.repair-orders.conversation-actions.send-inspection', $repairOrder);
    $previewUrl = route('operations.repair-orders.inspection-portal-preview', $repairOrder);

    $canSmsPortal = $inspectionSend['can_sms'];
    $canSendPortal = $canShare && $canSmsPortal;
    $portalSendBlockedReason = $inspectionSend['send_block_reason']
        ?? ((! $canSmsPortal && $canShare)
            ? ($inspectionSend['sms_block_reason'] ?? null)
            : null);
@endphp

@can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
    @if ($canShare)
        <div
            class="border-b border-slate-100 px-3 py-2.5"
            x-data="{
                copying: false,
                copied: false,
                sending: false,
                sendSuccess: '',
                error: '',
                async fetchPortalLink() {
                    const response = await fetch(@js($portalLinkUrl), {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (! response.ok) {
                        const payload = await response.json().catch(() => ({}));
                        throw new Error(payload.message || 'Could not create portal link.');
                    }

                    return (await response.json()).url;
                },
                async copyPortalLink() {
                    this.copying = true;
                    this.error = '';
                    this.copied = false;

                    try {
                        const url = await this.fetchPortalLink();

                        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                            await navigator.clipboard.writeText(url);
                        } else {
                            throw new Error('Clipboard is not available in this browser.');
                        }

                        this.copied = true;
                        setTimeout(() => { this.copied = false; }, 2500);
                    } catch (exception) {
                        this.error = exception?.message || 'Could not copy portal link.';
                    } finally {
                        this.copying = false;
                    }
                },
                async sendPortalLink() {
                    if (this.sending) {
                        return;
                    }

                    this.sending = true;
                    this.error = '';
                    this.sendSuccess = '';

                    try {
                        const response = await fetch(@js($sendPortalUrl), {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        });

                        const payload = await response.json().catch(() => ({}));

                        if (! response.ok) {
                            throw new Error(payload.message || 'Inspection link could not be sent.');
                        }

                        this.sendSuccess = 'Inspection link sent.';
                        setTimeout(() => { this.sendSuccess = ''; }, 3000);
                    } catch (exception) {
                        this.error = exception?.message || 'Inspection link could not be sent.';
                    } finally {
                        this.sending = false;
                    }
                },
            }"
        >
            <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-500">Inspection portal link</p>
            <p class="mt-0.5 text-xs leading-4 text-slate-500">
                Share {{ $findingCount }} recorded {{ str('finding')->plural($findingCount) }} so the customer can review photos and notes.
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                @if ($canSendPortal)
                    <button
                        type="button"
                        class="h-8 rounded-sm border border-slate-800 bg-slate-900 px-2.5 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
                        :disabled="sending"
                        @click="sendPortalLink()"
                    >
                        <span x-show="! sending">Send inspection link</span>
                        <span x-show="sending" x-cloak>Sending…</span>
                    </button>
                @elseif (filled($portalSendBlockedReason))
                    <div class="rounded-sm border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-950">
                        <p class="font-semibold">Send inspection unavailable</p>
                        <p class="mt-0.5 leading-4">{{ $portalSendBlockedReason }}</p>
                    </div>
                @endif
                <button
                    type="button"
                    class="h-8 rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950 disabled:opacity-60"
                    :disabled="copying"
                    @click="copyPortalLink()"
                >
                    <span x-show="! copying && ! copied">Copy portal link</span>
                    <span x-show="copying" x-cloak>Copying…</span>
                    <span x-show="copied" x-cloak>Copied</span>
                </button>
                <a
                    href="{{ $previewUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex h-8 items-center rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950"
                >
                    Preview customer inspection
                </a>
                <a
                    href="{{ route('operations.repair-orders.inspection.pdf', $repairOrder) }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex h-8 items-center rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950"
                >
                    Inspection PDF
                </a>
            </div>
            <p x-show="sendSuccess" x-text="sendSuccess" x-cloak class="mt-2 text-xs font-semibold text-emerald-700"></p>
            <div x-show="error" x-cloak class="mt-2 rounded-sm border border-rose-200 bg-rose-50 px-2.5 py-2 text-xs text-rose-950" role="alert">
                <p class="font-semibold">Inspection portal action failed</p>
                <p class="mt-0.5 leading-4" x-text="error"></p>
            </div>
        </div>
    @elseif (! $isTerminal)
        <p class="border-b border-slate-100 px-3 py-2 text-xs leading-4 text-slate-500">Record inspection findings before sharing a portal link.</p>
    @endif
@endcan
