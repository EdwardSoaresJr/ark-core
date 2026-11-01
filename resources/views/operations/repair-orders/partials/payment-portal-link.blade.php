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
            class="card-body"
            data-portal-link="payment"
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
            <div class="subheader">Payment portal link</div>
            <p class="small text-secondary mb-2">
                Issued invoice balance only. Send from Comms on the RO or Customer Hub - preview here before texting.
            </p>
            <div class="btn-list">
                @if ($canShare)
                    <button
                        type="button"
                        class="btn btn-sm"
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
                        class="btn btn-sm"
                    >
                        Preview customer payment
                    </a>
                @elseif (filled($portalSendBlockedReason))
                    <div class="alert alert-warning mb-0 w-100">
                        <div class="fw-bold">Payment link unavailable</div>
                        <div class="small">{{ $portalSendBlockedReason }}</div>
                    </div>
                @endif
            </div>
            <div x-show="error" x-cloak class="alert alert-danger mt-2 mb-0" role="alert">
                <div class="fw-bold">Payment link action failed</div>
                <div class="small" x-text="error"></div>
            </div>
        </div>
    @endif
@endcan
