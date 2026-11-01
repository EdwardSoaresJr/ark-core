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
            class="card-body"
            data-portal-link="inspection"
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
            <div class="subheader">Inspection portal link</div>
            <p class="small text-secondary mb-2">
                Share {{ $findingCount }} recorded {{ str('finding')->plural($findingCount) }} so the customer can review photos and notes.
            </p>
            <div class="btn-list">
                @if ($canSendPortal)
                    <button
                        type="button"
                        class="btn btn-sm btn-primary"
                        :disabled="sending"
                        @click="sendPortalLink()"
                    >
                        <span x-show="! sending">Send inspection link</span>
                        <span x-show="sending" x-cloak>Sending…</span>
                    </button>
                @elseif (filled($portalSendBlockedReason))
                    <div class="alert alert-warning mb-0 w-100">
                        <div class="fw-bold">Send inspection unavailable</div>
                        <div class="small">{{ $portalSendBlockedReason }}</div>
                    </div>
                @endif
                <button
                    type="button"
                    class="btn btn-sm"
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
                    class="btn btn-sm"
                >
                    Preview customer inspection
                </a>
                <a
                    href="{{ route('operations.repair-orders.inspection.pdf', $repairOrder) }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn btn-sm"
                >
                    Inspection PDF
                </a>
            </div>
            <div x-show="sendSuccess" x-text="sendSuccess" x-cloak class="small fw-bold text-success mt-2"></div>
            <div x-show="error" x-cloak class="alert alert-danger mt-2 mb-0" role="alert">
                <div class="fw-bold">Inspection portal action failed</div>
                <div class="small" x-text="error"></div>
            </div>
        </div>
    @elseif (! $isTerminal)
        <div class="card-body small text-secondary" data-portal-link="inspection">Record inspection findings before sharing a portal link.</div>
    @endif
@endcan
