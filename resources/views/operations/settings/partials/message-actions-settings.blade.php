@php
    use App\Ark\Operations\Communications\CommunicationsAccentColor;
    use App\Ark\Operations\Messaging\MessageActionKey;

    /** @var \App\Ark\Operations\Settings\ShopSettings $settings */
    $messageActions = is_array($settings->message_actions) ? $settings->message_actions : [];
    $savedColors = is_array($messageActions['colors'] ?? null) ? $messageActions['colors'] : [];
    $oldColors = old('message_actions.colors');
    $colors = is_array($oldColors) ? $oldColors : $savedColors;

    $blocks = [
        [
            'key' => MessageActionKey::Address->value,
            'title' => 'Send Address',
            'note' => 'The address text comes from shop settings. Color is only how this item looks in Actions.',
        ],
        [
            'key' => MessageActionKey::Hours->value,
            'title' => 'Send Hours',
            'note' => 'Hours text comes from shop hours. Color is only how this item looks in Actions.',
        ],
        [
            'key' => MessageActionKey::Pickup->value,
            'title' => 'Send Pickup Info',
            'note' => 'Uses the shop address, plus the after-hours note below when you fill it in.',
        ],
        [
            'key' => MessageActionKey::Tow->value,
            'title' => 'Send Tow Info',
            'note' => 'Shown in Actions once a tow phone is saved.',
        ],
        [
            'key' => MessageActionKey::Wifi->value,
            'title' => 'Send Wi-Fi',
            'note' => 'Shown in Actions once a network name is saved.',
        ],
    ];
@endphp

<div class="space-y-3">
    <div>
        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Message Actions</p>
        <p class="mt-1 text-xs leading-5 text-slate-500">
            Each block is one Actions menu item. Color is the bar on that item. Empty tow or Wi-Fi fields stay hidden.
        </p>
    </div>

    @foreach ($blocks as $block)
        @php
            $color = CommunicationsAccentColor::normalize($colors[$block['key']] ?? null);
        @endphp
        <article class="overflow-hidden rounded-sm border border-slate-300 bg-white" style="border-left: 4px solid {{ CommunicationsAccentColor::swatch($color) }}">
            <header class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-sm font-semibold text-slate-950">{{ $block['title'] }}</p>
                <p class="mt-0.5 text-xs leading-4 text-slate-500">{{ $block['note'] }}</p>
            </header>
            <div class="grid gap-3 p-3 sm:grid-cols-2">
                <label class="block sm:max-w-xs">
                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Menu color</span>
                    <select name="message_actions[colors][{{ $block['key'] }}]" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                        @foreach (CommunicationsAccentColor::options() as $option)
                            <option value="{{ $option['key'] }}" @selected($color === $option['key'])>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </label>

                @if ($block['key'] === MessageActionKey::Pickup->value)
                    <label class="block sm:col-span-2">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">After-hours pickup notes</span>
                        <textarea name="message_actions[after_hours_pickup]" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950" placeholder="Keys in drop box by Unit D door. Pay link must be complete first.">{{ old('message_actions.after_hours_pickup', $messageActions['after_hours_pickup'] ?? '') }}</textarea>
                    </label>
                @elseif ($block['key'] === MessageActionKey::Tow->value)
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Tow company</span>
                        <input type="text" name="message_actions[tow_company]" value="{{ old('message_actions.tow_company', $messageActions['tow_company'] ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950" placeholder="Pinky's Towing">
                    </label>
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Tow phone</span>
                        <input type="text" name="message_actions[tow_phone]" value="{{ old('message_actions.tow_phone', $messageActions['tow_phone'] ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950" placeholder="719-555-0100">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Tow notes</span>
                        <textarea name="message_actions[tow_notes]" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950" placeholder="Tell them it's for LugsNPlugs - Unit D.">{{ old('message_actions.tow_notes', $messageActions['tow_notes'] ?? '') }}</textarea>
                    </label>
                @elseif ($block['key'] === MessageActionKey::Wifi->value)
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Waiting room Wi-Fi network</span>
                        <input type="text" name="message_actions[wifi_ssid]" value="{{ old('message_actions.wifi_ssid', $messageActions['wifi_ssid'] ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                    </label>
                    <label class="block">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Wi-Fi password</span>
                        <input type="text" name="message_actions[wifi_password]" value="{{ old('message_actions.wifi_password', $messageActions['wifi_password'] ?? '') }}" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-950">
                    </label>
                @endif
            </div>
        </article>
    @endforeach
</div>
