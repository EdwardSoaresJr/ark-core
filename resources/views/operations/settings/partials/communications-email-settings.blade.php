@php
    $outbound = app(\App\Ark\Mail\OutboundTransactionalMail::class);
    $statusLabel = $outbound->statusLabel();
    $mailReady = $outbound->isReady();
@endphp

<div class="d-flex flex-column gap-3">
    <div>
        <div class="subheader">Customer email</div>
        <p class="text-secondary small">
            Estimates, invoices, and documents send through ARK Email when this shop is connected.
            Reply-to stays on this shop so customer replies reach you.
        </p>
        <p class="small fw-semibold mb-0">Status: {{ $statusLabel }}</p>
        @unless ($mailReady)
            <p class="alert alert-warning small mt-2 mb-0">
                Outbound customer email is not connected. You can still set reply-to. Messages will not send until ARK Email is connected.
                @unless (\App\Ark\Runtime\DemoInstall::isDemo())
                    <a href="{{ route('operations.settings.shop.edit', ['section' => 'ark-cloud']) }}" class="fw-semibold">Connect in ARK Platform</a>
                @endunless
            </p>
        @endunless
    </div>

    <form method="POST" action="{{ route('operations.settings.shop.email.update') }}" class="border rounded p-3 d-flex flex-column gap-3">
        @csrf
        @method('PATCH')

        <div>
            <div class="subheader">Reply-To</div>
            <p class="text-secondary small mb-0">
                Customer replies go here. Defaults to shop email when blank.
            </p>
            @unless (\Illuminate\Support\Facades\Schema::hasColumn('shop_settings', 'postmark_reply_to'))
                <p class="small mt-2 mb-0">
                    Replies go to {{ $settings->customerReplyToAddress() ?: 'the shop email' }}@if ($settings->customerReplyToName()) ({{ $settings->customerReplyToName() }})@endif.
                </p>
            @endunless
        </div>
        @if (\Illuminate\Support\Facades\Schema::hasColumn('shop_settings', 'postmark_reply_to'))

        <div class="row g-3">
            <label class="col-12 col-sm-6">
                <span class="form-label">Reply-to address</span>
                <input
                    type="email"
                    name="postmark_reply_to"
                    value="{{ old('postmark_reply_to', $settings->postmark_reply_to ?: ($settings->email ?: config('mail.reply_to.address'))) }}"
                    class="form-control"
                    placeholder="hello@yourshop.com"
                >
            </label>
            <label class="col-12 col-sm-6">
                <span class="form-label">Reply-to name</span>
                <input
                    type="text"
                    name="postmark_reply_to_name"
                    value="{{ old('postmark_reply_to_name', $settings->postmark_reply_to_name ?: config('mail.reply_to.name')) }}"
                    class="form-control"
                    placeholder="{{ $settings->shop_name ?: 'Shop name' }}"
                >
            </label>
        </div>

        <div class="d-flex justify-content-end border-top pt-3">
            <button type="submit" class="btn btn-primary">
                Save reply-to settings
            </button>
        </div>
        @endif
    </form>
</div>
