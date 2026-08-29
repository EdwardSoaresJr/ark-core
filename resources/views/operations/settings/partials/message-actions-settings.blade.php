<?php

/** @var \App\Ark\Operations\Settings\ShopSettings $settings */
$messageActions = is_array($settings->message_actions) ? $settings->message_actions : [];
?>

<div class="space-y-3 rounded-sm border border-slate-200 bg-slate-50/60 p-3">
    <div class="border-b border-slate-200 pb-3">
        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Message Actions</p>
        <p class="mt-1 text-xs leading-5 text-slate-500">
            Extras for one-tap advisor SMS (Tow, Wi-Fi, after-hours pickup). Address and hours already come from shop settings.
            Empty fields stay hidden on the Quick Reply rail.
        </p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="block">
            <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Tow company</span>
            <input type="text" name="message_actions[tow_company]" value="{{ old('message_actions.tow_company', $messageActions['tow_company'] ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950" placeholder="Pinky's Towing">
        </label>
        <label class="block">
            <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Tow phone</span>
            <input type="text" name="message_actions[tow_phone]" value="{{ old('message_actions.tow_phone', $messageActions['tow_phone'] ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950" placeholder="719-555-0100">
        </label>
    </div>

    <label class="block">
        <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Tow notes</span>
        <textarea name="message_actions[tow_notes]" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950" placeholder="Tell them it's for Demo Auto Repair — Unit D.">{{ old('message_actions.tow_notes', $messageActions['tow_notes'] ?? '') }}</textarea>
    </label>

    <div class="grid gap-3 sm:grid-cols-2">
        <label class="block">
            <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Waiting room Wi-Fi network</span>
            <input type="text" name="message_actions[wifi_ssid]" value="{{ old('message_actions.wifi_ssid', $messageActions['wifi_ssid'] ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
        </label>
        <label class="block">
            <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">Wi-Fi password</span>
            <input type="text" name="message_actions[wifi_password]" value="{{ old('message_actions.wifi_password', $messageActions['wifi_password'] ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
        </label>
    </div>

    <label class="block">
        <span class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">After-hours pickup notes</span>
        <textarea name="message_actions[after_hours_pickup]" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950" placeholder="Keys in drop box by Unit D door. Pay link must be complete first.">{{ old('message_actions.after_hours_pickup', $messageActions['after_hours_pickup'] ?? '') }}</textarea>
    </label>
</div>
