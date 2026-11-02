@php
    use App\Ark\Operations\Messaging\Messenger\MessengerChannelConnection;
    use App\Ark\Operations\Messaging\Messenger\MetaMessengerMessageTag;

    $channelConnection = MessengerChannelConnection::forCurrentShop();
    $shopConnection = $channelConnection->shopConnection();
    $statusTone = $channelConnection->statusTone();
    $webhookToneClasses = [
        'success' => 'alert-success',
        'warning' => 'alert-warning',
        'danger' => 'alert-danger',
        'muted' => 'alert-secondary',
    ];
@endphp

<form
    method="POST"
    action="{{ route('operations.settings.shop.telephony.update') }}"
    class="d-flex flex-column gap-3"
>
    @csrf
    @method('PATCH')
    <input type="hidden" name="communications_tab" value="messenger">

    <div class="border rounded p-3 d-flex flex-column gap-3">
        <div class="border-bottom pb-3">
            <div class="subheader">Facebook Messenger</div>
            <p class="text-secondary small mb-0">
                Messenger appears as a conversation channel in ARK. Outbound and inbound transport is not bundled in Core.
            </p>
        </div>

        <div class="alert mb-0 {{ $webhookToneClasses[$statusTone] ?? $webhookToneClasses['muted'] }}">
            <p class="small fw-bold text-uppercase mb-1">Messenger</p>
            <p class="fw-semibold mb-2">{{ $channelConnection->statusLabel() }}</p>
            <p class="small text-secondary mb-0">
                Messenger outbound is not configured. Historical Messenger conversations remain readable when linked to customers.
            </p>
        </div>

        <label class="form-check mb-0">
            <input
                type="checkbox"
                name="channels[messenger][enabled]"
                value="1"
                class="form-check-input"
                @checked(old('channels.messenger.enabled', $shopConnection->isEnabled()))
            >
            <span class="form-check-label">
                Show Messenger in inbound queue filters
                <span class="small text-secondary">- display only; transport is not active</span>
            </span>
        </label>

        <label class="d-block">
            <span class="form-label">Default outside-window message tag</span>
            <select
                name="channels[messenger][outside_window_tag]"
                class="form-select"
            >
                <option value="">None</option>
                @foreach (MetaMessengerMessageTag::cases() as $tag)
                    <option
                        value="{{ $tag->value }}"
                        @selected(old('channels.messenger.outside_window_tag', $shopConnection->outsideWindowTag()?->value) === $tag->value)
                    >{{ $tag->label() }}</option>
                @endforeach
            </select>
            <span class="form-hint">
                Reserved for future Messenger transport configuration.
            </span>
        </label>
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <p class="small text-secondary mb-0">
            Health check:
            <x-operations.learn.guide-link role="admin" article="comms-health-check" label="Communications health check" class="btn btn-link p-0 align-baseline small" />
        </p>
        <button type="submit" class="btn btn-primary">
            Save channel settings
        </button>
    </div>
</form>
