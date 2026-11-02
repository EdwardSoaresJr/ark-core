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

<div class="d-flex flex-column gap-3">
    <div>
        <div class="subheader">Message Actions</div>
        <p class="text-secondary small mb-0">
            Each block is one Actions menu item. Color is the bar on that item. Empty tow or Wi-Fi fields stay hidden.
        </p>
    </div>

    @foreach ($blocks as $block)
        @php
            $color = CommunicationsAccentColor::normalize($colors[$block['key']] ?? null);
        @endphp
        <article class="card card-sm" style="border-left: 4px solid {{ CommunicationsAccentColor::swatch($color) }}">
            <header class="card-header d-block">
                <h4 class="mb-1">{{ $block['title'] }}</h4>
                <p class="text-secondary small mb-0">{{ $block['note'] }}</p>
            </header>
            <div class="card-body">
                <div class="row g-3">
                    <label class="col-12 col-sm-6">
                        <span class="form-label">Menu color</span>
                        <select name="message_actions[colors][{{ $block['key'] }}]" class="form-select">
                            @foreach (CommunicationsAccentColor::options() as $option)
                                <option value="{{ $option['key'] }}" @selected($color === $option['key'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </label>

                    @if ($block['key'] === MessageActionKey::Pickup->value)
                        <label class="col-12">
                            <span class="form-label">After-hours pickup notes</span>
                            <textarea name="message_actions[after_hours_pickup]" rows="2" class="form-control" placeholder="Keys in drop box by Unit D door. Pay link must be complete first.">{{ old('message_actions.after_hours_pickup', $messageActions['after_hours_pickup'] ?? '') }}</textarea>
                        </label>
                    @elseif ($block['key'] === MessageActionKey::Tow->value)
                        <label class="col-12 col-sm-6">
                            <span class="form-label">Tow company</span>
                            <input type="text" name="message_actions[tow_company]" value="{{ old('message_actions.tow_company', $messageActions['tow_company'] ?? '') }}" class="form-control" placeholder="Pinky's Towing">
                        </label>
                        <label class="col-12 col-sm-6">
                            <span class="form-label">Tow phone</span>
                            <input type="text" name="message_actions[tow_phone]" value="{{ old('message_actions.tow_phone', $messageActions['tow_phone'] ?? '') }}" class="form-control" placeholder="719-555-0100">
                        </label>
                        <label class="col-12">
                            <span class="form-label">Tow notes</span>
                            <textarea name="message_actions[tow_notes]" rows="2" class="form-control" placeholder="Tell them it's for LugsNPlugs - Unit D.">{{ old('message_actions.tow_notes', $messageActions['tow_notes'] ?? '') }}</textarea>
                        </label>
                    @elseif ($block['key'] === MessageActionKey::Wifi->value)
                        <label class="col-12 col-sm-6">
                            <span class="form-label">Waiting room Wi-Fi network</span>
                            <input type="text" name="message_actions[wifi_ssid]" value="{{ old('message_actions.wifi_ssid', $messageActions['wifi_ssid'] ?? '') }}" class="form-control">
                        </label>
                        <label class="col-12 col-sm-6">
                            <span class="form-label">Wi-Fi password</span>
                            <input type="text" name="message_actions[wifi_password]" value="{{ old('message_actions.wifi_password', $messageActions['wifi_password'] ?? '') }}" class="form-control">
                        </label>
                    @endif
                </div>
            </div>
        </article>
    @endforeach
</div>
