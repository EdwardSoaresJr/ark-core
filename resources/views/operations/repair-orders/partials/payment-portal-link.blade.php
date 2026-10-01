@php
    use App\Ark\Operations\Messaging\RepairOrderConversationSendProjection;
    use App\Ark\Operations\Payments\CardPresentCaptureProjection;

    $square = app(CardPresentCaptureProjection::class);
    $sendProjection = app(RepairOrderConversationSendProjection::class)->forRepairOrder($repairOrder, auth()->user());
    $paymentSend = $sendProjection['payment'];

    $canShare = $square->portalPayEnabled() && ($paymentSend['send_block_reason'] ?? null) === null;
    $portalLinkUrl = route('operations.repair-orders.payment-portal-link', $repairOrder);
    $previewUrl = route('operations.repair-orders.payment-portal-preview', $repairOrder);
    $portalSendBlockedReason = $paymentSend['send_block_reason']
        ?? ((! ($paymentSend['can_sms'] ?? false) && ! ($paymentSend['can_email'] ?? false) && $square->portalPayEnabled())
            ? ($paymentSend['sms_block_reason'] ?? $paymentSend['email_block_reason'])
            : null);
@endphp

@can(App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value)
    @if ($square->portalPayEnabled())
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
                        throw new Error(payload.message || 'Could not create payment link.');
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
                        this.error = exception?.message || 'Could not copy payment link.';
                    } finally {
                        this.copying = false;
                    }
                },
            }"
        >
            <p class="text-xs font-bold uppercase tracking-[0.08em] text-slate-500">Payment portal link</p>
            <p class="mt-0.5 text-xs leading-4 text-slate-500">
                Issued invoice balance only. Send from Comms on the RO or Customer Hub - preview here before texting.
            </p>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                @if ($canShare)
                    <button
                        type="button"
                        class="h-8 rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950 disabled:opacity-60"
                        :disabled="copying"
                        @click="copyPortalLink()"
                    >
                        <span x-show="! copying && ! copied">Copy payment link</span>
                        <span x-show="copying" x-cloak>Copying…</span>
                        <span x-show="copied" x-cloak>Copied</span>
                    </button>
                    <a
                        href="{{ $previewUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex h-8 items-center rounded-sm border border-slate-300 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-400 hover:text-slate-950"
                    >
                        Preview customer payment
                    </a>
                @elseif (filled($portalSendBlockedReason))
                    <div class="rounded-sm border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-950">
                        <p class="font-semibold">Payment link unavailable</p>
                        <p class="mt-0.5 leading-4">{{ $portalSendBlockedReason }}</p>
                    </div>
                @endif
            </div>
            <div x-show="error" x-cloak class="mt-2 rounded-sm border border-rose-200 bg-rose-50 px-2.5 py-2 text-xs text-rose-950" role="alert">
                <p class="font-semibold">Payment link action failed</p>
                <p class="mt-0.5 leading-4" x-text="error"></p>
            </div>
        </div>
    @endif
@endcan
