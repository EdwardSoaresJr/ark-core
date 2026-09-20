@php
    /** @var \App\Ark\Operations\Settings\ShopSettings $settings */
    $callFlow = \App\Ark\Operations\Telephony\TelephonyCallFlowSettings::fromShopSettings($settings);
    $callFlowConfig = $callFlow->toArray();
@endphp

<section x-show="active === 'customer-messaging'" x-cloak>
    <div class="space-y-4">
        <div class="border-b border-slate-200 pb-2">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Customer Messaging</p>
            <h2 class="text-base font-black text-slate-950">Shop messaging defaults</h2>
            <p class="mt-0.5 text-xs leading-5 text-slate-500">
                Reusable snippets and advisor messaging behavior. Phone, Texting, and Voice service configuration lives in ARK Platform.
            </p>
        </div>

        <form method="POST" action="{{ route('operations.settings.shop.customer-messaging.update') }}" class="space-y-4">
            @csrf
            @method('PATCH')

            @include('operations.settings.partials.message-actions-settings', ['settings' => $settings])

            <div class="space-y-3 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
                <div class="border-b border-slate-200 pb-2">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Google review link</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Used when advisors send a review request after a completed repair.
                    </p>
                </div>
                <label class="block">
                    <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Review URL</span>
                    <input
                        type="url"
                        name="google_reviews_url"
                        value="{{ old('google_reviews_url', $settings->google_reviews_url) }}"
                        class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                        placeholder="https://g.page/r/…"
                    >
                </label>
            </div>

            <div class="space-y-3 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
                <div class="border-b border-slate-200 pb-2">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Email reply-to</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Customer replies to estimate and invoice email go here.
                        <a href="{{ route('operations.settings.shop.edit', ['section' => 'ark-cloud']) }}" class="font-semibold underline">Connect ARK Email in ARK Platform</a>
                        to send outbound email.
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Reply-to address</span>
                        <input
                            type="email"
                            name="postmark_reply_to"
                            value="{{ old('postmark_reply_to', $settings->postmark_reply_to ?: ($settings->email ?: config('mail.reply_to.address'))) }}"
                            class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                            placeholder="hello@yourshop.com"
                        >
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Reply-to name</span>
                        <input
                            type="text"
                            name="postmark_reply_to_name"
                            value="{{ old('postmark_reply_to_name', $settings->postmark_reply_to_name ?: config('mail.reply_to.name')) }}"
                            class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                            placeholder="{{ $settings->shop_name ?: 'Shop name' }}"
                        >
                    </label>
                </div>
            </div>

            <div class="rounded-sm border border-slate-200 bg-slate-50 p-3 space-y-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Advisor messaging behavior</p>
                <label class="flex items-start gap-2 text-sm text-slate-800">
                    <input type="hidden" name="telephony_call_flow[comms_attention_gate_enabled]" value="0">
                    <input
                        type="checkbox"
                        name="telephony_call_flow[comms_attention_gate_enabled]"
                        value="1"
                        @checked(old('telephony_call_flow.comms_attention_gate_enabled', $callFlowConfig['comms_attention_gate_enabled'] ?? true))
                        class="mt-0.5 rounded border-slate-300"
                    >
                    <span>Block other ARK pages until the Work communications queue is cleared (Mark Handled / Mark Read / Reply). Incoming call and text popups still appear either way.</span>
                </label>
                <label class="flex items-start gap-2 text-sm text-slate-800">
                    <input type="hidden" name="telephony_call_flow[comms_escalation_enabled]" value="0">
                    <input
                        type="checkbox"
                        name="telephony_call_flow[comms_escalation_enabled]"
                        value="1"
                        @checked(old('telephony_call_flow.comms_escalation_enabled', $callFlowConfig['comms_escalation_enabled'] ?? true))
                        class="mt-0.5 rounded border-slate-300"
                    >
                    <span>Text all active advisors when calls, texts, or website leads stay unhandled (requires advisor phone on staff profile).</span>
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Escalation delay (minutes)</span>
                        <input
                            type="number"
                            min="1"
                            max="30"
                            name="telephony_call_flow[comms_escalation_delay_minutes]"
                            value="{{ old('telephony_call_flow.comms_escalation_delay_minutes', $callFlowConfig['comms_escalation_delay_minutes'] ?? 3) }}"
                            class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                        >
                    </label>
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Escalation cooldown (minutes)</span>
                        <input
                            type="number"
                            min="5"
                            max="240"
                            name="telephony_call_flow[comms_escalation_cooldown_minutes]"
                            value="{{ old('telephony_call_flow.comms_escalation_cooldown_minutes', $callFlowConfig['comms_escalation_cooldown_minutes'] ?? 30) }}"
                            class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                        >
                    </label>
                </div>
                <label class="flex items-start gap-2 text-sm text-slate-800">
                    <input type="hidden" name="telephony_call_flow[comms_browser_notifications_enabled]" value="0">
                    <input
                        type="checkbox"
                        name="telephony_call_flow[comms_browser_notifications_enabled]"
                        value="1"
                        @checked(old('telephony_call_flow.comms_browser_notifications_enabled', $callFlowConfig['comms_browser_notifications_enabled'] ?? true))
                        class="mt-0.5 rounded border-slate-300"
                    >
                    <span>Browser notifications + chime when ARK tab is open (advisor must allow notifications once).</span>
                </label>
            </div>

            <div class="rounded-sm border border-slate-200 bg-slate-50 p-3 space-y-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Missed call text-back</p>
                <p class="text-xs text-slate-500">Shop-owned SMS templates sent after a missed inbound call when ARK Texting is active.</p>
                <label class="flex items-start gap-2 text-sm text-slate-800">
                    <input type="hidden" name="telephony_call_flow[missed_call_rescue_enabled]" value="0">
                    <input
                        type="checkbox"
                        name="telephony_call_flow[missed_call_rescue_enabled]"
                        value="1"
                        @checked(old('telephony_call_flow.missed_call_rescue_enabled', $callFlowConfig['missed_call_rescue_enabled'] ?? false))
                        class="mt-0.5 rounded border-slate-300"
                    >
                    <span>Enable missed-call SMS</span>
                </label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Delay (seconds)</span>
                        <input
                            type="number"
                            min="10"
                            max="3600"
                            name="telephony_call_flow[missed_call_rescue_delay_seconds]"
                            value="{{ old('telephony_call_flow.missed_call_rescue_delay_seconds', $callFlowConfig['missed_call_rescue_delay_seconds'] ?? 120) }}"
                            class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                        >
                    </label>
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Cooldown (minutes)</span>
                        <input
                            type="number"
                            min="30"
                            max="4320"
                            name="telephony_call_flow[missed_call_rescue_cooldown_minutes]"
                            value="{{ old('telephony_call_flow.missed_call_rescue_cooldown_minutes', $callFlowConfig['missed_call_rescue_cooldown_minutes'] ?? 60) }}"
                            class="mt-1 h-9 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                        >
                    </label>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">TextBack template (open)</span>
                        <textarea
                            name="telephony_call_flow[missed_call_rescue_text_open]"
                            rows="3"
                            class="mt-1 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                            placeholder="Hey! This is @{{business.name}}. Sorry we missed your call…"
                        >{{ old('telephony_call_flow.missed_call_rescue_text_open', $callFlowConfig['missed_call_rescue_text_open'] ?? '') }}</textarea>
                    </label>
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">TextBack template (closed)</span>
                        <textarea
                            name="telephony_call_flow[missed_call_rescue_text_closed]"
                            rows="3"
                            class="mt-1 w-full rounded-sm border-slate-300 text-sm text-slate-800"
                            placeholder="Hey! This is @{{business.name}}. Sorry we missed your call — we're currently closed…"
                        >{{ old('telephony_call_flow.missed_call_rescue_text_closed', $callFlowConfig['missed_call_rescue_text_closed'] ?? '') }}</textarea>
                    </label>
                </div>
                <p class="text-xs text-slate-500">Tokens: <code class="text-[11px]">@{{business.name}}</code> <code class="text-[11px]">@{{caller.number}}</code>. Empty templates use shop defaults.</p>
            </div>

            <div class="flex justify-end border-t border-slate-200 pt-4">
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-sm bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                    Save Customer Messaging
                </button>
            </div>
        </form>
    </div>
</section>
