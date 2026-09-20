@php
    use App\Ark\Operations\Messaging\RepairOrderConversationSendProjection;

    $integrations = app(App\Ark\Operations\Settings\ShopIntegrationCredentials::class);
    $sendProjection = app(RepairOrderConversationSendProjection::class)->forRepairOrder($repairOrder, auth()->user());
    $estimateSend = $sendProjection['estimate'];

    $canShare = ! $isTerminal && $repairOrder->lines->isNotEmpty();
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
            class="border-b border-slate-100 px-3 py-2.5"
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
            <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-500">Customer portal link</p>
            <p class="mt-0.5 text-xs leading-4 text-slate-500">Share this link so the customer can approve recommended work on this estimate.</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
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
                            'timingFluidsMissing' => $estimateSend['timing_fluids_missing'] ?? false,
                            'timingFluidsMessage' => $estimateSend['timing_fluids_message'] ?? null,
                            'timingFluidsDetail' => $estimateSend['timing_fluids_detail'] ?? null,
                            'addVinUrl' => route('operations.repair-orders.show', $repairOrder).'#ro-identity-band',
                        ]))"
                    >
                        <div class="ops-comms-menu" x-ref="portalMenu">
                            <button
                                type="button"
                                x-ref="portalMenuTrigger"
                                @click.stop="toggleMenu()"
                                :disabled="sending"
                                class="ops-comms-menu__trigger h-8 rounded-sm border border-slate-800 bg-slate-900 px-2.5 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
                                :aria-expanded="menuOpen"
                                aria-haspopup="menu"
                            >
                                <span>Send Portal</span>
                                <span class="ops-comms-menu__caret" aria-hidden="true">▾</span>
                            </button>
                            <template x-teleport="body">
                                <div
                                    x-show="menuOpen"
                                    x-ref="portalMenuPanel"
                                    :style="menuStyle"
                                    class="ops-comms-menu__panel ops-comms-menu__panel--floating"
                                    role="menu"
                                    @click.stop
                                >
                                    <button
                                        type="button"
                                        role="menuitem"
                                        class="ops-comms-menu__item"
                                        :class="{ 'opacity-50': ! canSms && ! sending }"
                                        :disabled="sending"
                                        @click.stop="sendPortal('sms')"
                                    >SMS</button>
                                    <button
                                        type="button"
                                        role="menuitem"
                                        class="ops-comms-menu__item"
                                        :class="{ 'opacity-50': ! canEmail && ! sending }"
                                        :disabled="sending"
                                        @click.stop="sendPortal('email')"
                                    >Email</button>
                                    <button
                                        type="button"
                                        role="menuitem"
                                        class="ops-comms-menu__item"
                                        :class="{ 'opacity-50': (! canSms || ! canEmail) && ! sending }"
                                        :disabled="sending"
                                        @click.stop="sendPortal('both')"
                                    >Both</button>
                                </div>
                            </template>
                        </div>
                        <div x-show="vinWarningOpen" x-cloak class="ops-estimate-vin-warning mt-2">
                            <p class="ops-estimate-vin-warning-title">VIN missing</p>
                            <p class="ops-estimate-vin-warning-copy">
                                Parts lookup, labor guides, service history accuracy, and OEM information may be affected.
                            </p>
                            <div class="ops-estimate-vin-warning-actions">
                                <a :href="addVinUrl" class="ops-estimate-email-form-btn ops-estimate-email-form-btn--secondary" @click="cancelVinWarning()">Add VIN</a>
                                <button type="button" class="ops-estimate-email-form-btn ops-estimate-email-form-btn--primary" @click="continueWithoutVin()">Continue anyway</button>
                            </div>
                        </div>
                        <div x-show="fluidsWarningOpen" x-cloak class="ops-estimate-vin-warning mt-2">
                            <p class="ops-estimate-vin-warning-title" x-text="timingFluidsMessage"></p>
                            <p class="ops-estimate-vin-warning-copy" x-text="timingFluidsDetail"></p>
                            <div class="ops-estimate-vin-warning-actions">
                                <button type="button" class="ops-estimate-email-form-btn ops-estimate-email-form-btn--secondary" @click="cancelVinWarning()">Add fluids</button>
                                <button type="button" class="ops-estimate-email-form-btn ops-estimate-email-form-btn--primary" @click="continueWithoutTimingFluids()">Continue anyway</button>
                            </div>
                        </div>
                        <div x-show="error" x-cloak class="mt-2 rounded-sm border border-rose-200 bg-rose-50 px-2.5 py-2 text-xs text-rose-950" role="alert">
                            <p class="font-semibold">Could not send portal link</p>
                            <p class="mt-0.5 leading-4" x-text="error"></p>
                        </div>
                        <p x-show="success" x-text="success" x-cloak class="mt-2 text-xs font-semibold text-emerald-700"></p>
                    </div>
                @elseif (filled($portalSendBlockedReason))
                    <div class="rounded-sm border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-950">
                        <p class="font-semibold">Send Portal unavailable</p>
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
                    href="{{ route('operations.repair-orders.portal-preview', $repairOrder) }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex h-8 items-center rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950"
                >
                    Preview customer estimate
                </a>
                <a
                    href="{{ $vehicleHistoryUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex h-8 items-center rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950"
                >
                    View vehicle history
                </a>
            </div>
            <div x-show="error" x-cloak class="mt-2 rounded-sm border border-rose-200 bg-rose-50 px-2.5 py-2 text-xs text-rose-950" role="alert">
                <p class="font-semibold">Portal link action failed</p>
                <p class="mt-0.5 leading-4" x-text="error"></p>
            </div>
        </div>
    @elseif (! $isTerminal)
        <p class="border-b border-slate-100 px-3 py-2 text-xs leading-4 text-slate-500">Add estimate lines before sharing a portal link.</p>
    @endif
@endcan
