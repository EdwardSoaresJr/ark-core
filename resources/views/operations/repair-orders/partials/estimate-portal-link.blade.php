@php
    use App\Ark\Operations\Messaging\RepairOrderConversationSendProjection;

    $integrations = app(App\Ark\Operations\Settings\ShopIntegrationCredentials::class);
    $sendProjection = app(RepairOrderConversationSendProjection::class)->forRepairOrder($repairOrder, auth()->user());
    $estimateSend = $sendProjection['estimate'];

    $canShare = $repairOrder->lines->isNotEmpty();
    $portalLinkUrl = route('operations.repair-orders.estimate-portal-link', $repairOrder);
    $sendPortalUrl = route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder);
    $vehicleHistoryUrl = $repairOrder->vehicle
        ? route('operations.repair-orders.portal-session', [
            'repairOrder' => $repairOrder,
            'return' => route('portal.vehicles.show', $repairOrder->vehicle, absolute: false),
        ])
        : route('operations.repair-orders.portal-session', $repairOrder);

    $customerEmail = trim((string) ($repairOrder->customer?->email ?? ''));
    $canSmsPortal = $estimateSend['can_sms'];
    $canEmailPortal = $estimateSend['can_email'];
    $canSendPortal = $canShare && ($canSmsPortal || $canEmailPortal);
    $portalSendBlockedReason = $estimateSend['send_block_reason']
        ?? ((! $canSmsPortal && ! $canEmailPortal && $canShare)
            ? ($estimateSend['sms_block_reason'] ?? $estimateSend['email_block_reason'])
            : null);
    $missingVin = $estimateSend['missing_vin'];
@endphp

@can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
    @if ($canShare)
        <div
            class="card-body"
            data-portal-link="estimate"
            x-data="{
                copying: false,
                copied: false,
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

                    const payload = await response.json();

                    return payload.url;
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
            }"
        >
            <div class="subheader">Customer portal link</div>
            <p class="small text-secondary mb-2">Share this link so the customer can approve recommended work on this estimate.</p>
            <div class="btn-list">
                @if ($canSendPortal)
                    <div
                        x-data="arkPortalSendMenu(@js([
                            'sendUrl' => $sendPortalUrl,
                            'customerEmail' => $customerEmail,
                            'canSms' => $canSmsPortal,
                            'canEmail' => $canEmailPortal,
                            'smsBlockReason' => $estimateSend['sms_block_reason'],
                            'emailBlockReason' => $estimateSend['email_block_reason'],
                            'sendBlockReason' => $estimateSend['send_block_reason'],
                            'missingVin' => $missingVin,
                            'vinBlockMessage' => $estimateSend['vin_block_message'],
                            'addVinUrl' => route('operations.repair-orders.show', $repairOrder).'#ro-identity-band',
                        ]))"
                        data-portal-send-menu
                    >
                        <div x-ref="portalMenu">
                            <button
                                type="button"
                                x-ref="portalMenuTrigger"
                                @click.stop="toggleMenu()"
                                :disabled="sending"
                                class="btn btn-sm btn-primary dropdown-toggle"
                                :aria-expanded="menuOpen"
                                aria-haspopup="menu"
                            >
                                Send Portal
                            </button>
                            <template x-teleport="body">
                                <div
                                    x-show="menuOpen"
                                    x-ref="portalMenuPanel"
                                    :style="menuStyle"
                                    class="dropdown-menu show position-fixed"
                                    role="menu"
                                    @click.stop
                                >
                                    <button
                                        type="button"
                                        role="menuitem"
                                        class="dropdown-item"
                                        :class="{ 'opacity-50': ! canSms && ! sending }"
                                        :disabled="sending"
                                        @click.stop="sendPortal('sms')"
                                    >SMS</button>
                                    <button
                                        type="button"
                                        role="menuitem"
                                        class="dropdown-item"
                                        :class="{ 'opacity-50': ! canEmail && ! sending }"
                                        :disabled="sending"
                                        @click.stop="sendPortal('email')"
                                    >Email</button>
                                    <button
                                        type="button"
                                        role="menuitem"
                                        class="dropdown-item"
                                        :class="{ 'opacity-50': (! canSms || ! canEmail) && ! sending }"
                                        :disabled="sending"
                                        @click.stop="sendPortal('both')"
                                    >Both</button>
                                </div>
                            </template>
                        </div>
                        <div x-show="vinWarningOpen" x-cloak class="alert alert-warning mt-2 mb-0" data-estimate-vin-warning>
                            <div class="fw-bold">VIN missing</div>
                            <div class="small">
                                Parts lookup, labor guides, service history accuracy, and OEM information may be affected.
                            </div>
                            <div class="btn-list mt-2">
                                <a :href="addVinUrl" class="btn btn-sm" @click="cancelVinWarning()">Add VIN</a>
                                <button type="button" class="btn btn-sm btn-warning" @click="continueWithoutVin()">Continue anyway</button>
                            </div>
                        </div>
                        <div x-show="error" x-cloak class="alert alert-danger mt-2 mb-0" role="alert">
                            <div class="fw-bold">Could not send portal link</div>
                            <div class="small" x-text="error"></div>
                        </div>
                        <div x-show="success" x-text="success" x-cloak class="small fw-bold text-success mt-2"></div>
                    </div>
                @elseif (filled($portalSendBlockedReason))
                    <div class="alert alert-warning mb-0 w-100">
                        <div class="fw-bold">Send Portal unavailable</div>
                        <div class="small">{{ $portalSendBlockedReason }}</div>
                        @if ($portalSendBlockedReason === \App\Ark\Operations\RepairOrders\RepairOrder::ESTIMATE_SEND_DRAFT_ONLY_MESSAGE)
                            <a href="{{ $repairOrder->estimateConcernReviewUrl() }}" class="d-inline-block mt-1 fw-bold">Review concerns</a>
                        @endif
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
                    href="{{ route('operations.repair-orders.portal-preview', $repairOrder) }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn btn-sm"
                >
                    Preview customer estimate
                </a>
                <a
                    href="{{ $vehicleHistoryUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn btn-sm"
                >
                    View vehicle history
                </a>
            </div>
            <div x-show="error" x-cloak class="alert alert-danger mt-2 mb-0" role="alert">
                <div class="fw-bold">Portal link action failed</div>
                <div class="small" x-text="error"></div>
            </div>
        </div>
    @elseif (! $isTerminal)
        <div class="card-body small text-secondary" data-portal-link="estimate">Add estimate lines before sharing a portal link.</div>
    @endif
@endcan
