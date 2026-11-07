{{--
    The Shop Settings section menu. With no $current it switches sections inside the Shop Settings page
    (Alpine `active` / `setActive`). With $current set, it is shown on a settings page of its own and
    every item is a link, the current one highlighted. Website and Book Service Builder are pages of a
    hosting product (ARK Sites, ARK Shop); they appear only where that product registered the route.
--}}
@php
    $current ??= null;
    $productUrl = fn (string $route): ?string => \Illuminate\Support\Facades\Route::has($route) ? route($route) : null;
    $settingsNav = [
        'Your shop' => [
            ['key' => 'general', 'label' => 'Shop Identity'],
            ['key' => 'operations', 'label' => 'Appointments & Setup'],
            ['key' => 'staff', 'label' => 'Staff', 'can' => App\Ark\Runtime\Authorization\ArkCapability::StaffManage->value],
        ],
        'Website & booking' => [
            ['key' => 'website', 'label' => 'Website', 'href' => $productUrl('sites.settings.index'), 'hidden' => $productUrl('sites.settings.index') === null, 'can' => App\Ark\Runtime\Authorization\ArkCapability::SettingsManage->value],
            ['key' => 'booking-builder', 'label' => 'Book Service Builder', 'href' => $productUrl('booking.builder'), 'hidden' => $productUrl('booking.builder') === null, 'can' => App\Ark\Runtime\Authorization\ArkCapability::SettingsManage->value],
            ['key' => 'booking', 'label' => 'Online Requests', 'href' => route('operations.settings.booking')],
        ],
        'Money' => [
            ['key' => 'financial', 'label' => 'Financial Rules'],
            ['key' => 'overhead', 'label' => 'Shop Overhead'],
            ['key' => 'excellence', 'label' => 'Owner Targets & Reporting'],
        ],
        'Documents & workflow' => [
            ['key' => 'estimates', 'label' => 'Documents / Disclaimers'],
            ['key' => 'workflow', 'label' => 'Workflow Defaults'],
            ['key' => 'printing', 'label' => 'Label Printing'],
        ],
        'Connected services' => [
            ['key' => 'payments', 'label' => 'Square Payments'],
            ['key' => 'partstech', 'label' => 'Parts catalogs'],
            ['key' => 'communications', 'label' => 'Communications'],
            ['key' => 'stations', 'label' => 'Stations & Phones', 'href' => route('operations.shop.communications')],
            ['key' => 'ark-cloud', 'label' => 'ARK Platform', 'hidden' => \App\Ark\Runtime\DemoInstall::isDemo()],
        ],
    ];
    $settingsNav = array_map(fn (array $items): array => array_values(array_filter(
        $items,
        fn (array $item): bool => ! ($item['hidden'] ?? false) && (! isset($item['can']) || auth()->user()?->can($item['can'])),
    )), $settingsNav);
    $settingsNav = array_filter($settingsNav);
    $linkTo = fn (array $item): string => $item['href'] ?? route('operations.settings.shop.edit', ['section' => $item['key']]);
@endphp

<div class="col-12 d-lg-none">
    <label for="settings-section" class="form-label">Section</label>
    @if ($current === null)
        <select id="settings-section" class="form-select" :value="active" @change="$event.target.selectedOptions[0].dataset.href ? window.location.assign($event.target.selectedOptions[0].dataset.href) : setActive($event.target.value)">
            @foreach ($settingsNav as $group => $items)
                <optgroup label="{{ $group }}">
                    @foreach ($items as $item)
                        <option value="{{ $item['key'] }}" @isset($item['href']) data-href="{{ $item['href'] }}" @endisset>{{ $item['label'] }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    @else
        <select id="settings-section" class="form-select" onchange="window.location.assign(this.value)">
            @foreach ($settingsNav as $group => $items)
                <optgroup label="{{ $group }}">
                    @foreach ($items as $item)
                        <option value="{{ $linkTo($item) }}" @selected($item['key'] === $current)>{{ $item['label'] }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    @endif
</div>
<div class="col-12 col-lg-3 col-xxl-2 d-none d-lg-block">
    <nav class="card sticky-lg-top" style="top: 1rem" aria-label="Shop settings sections">
        @foreach ($settingsNav as $group => $items)
            <div class="card-header py-2"><div class="subheader">{{ $group }}</div></div>
            <div class="list-group list-group-flush">
                @foreach ($items as $item)
                    @if ($current !== null)
                        <a
                            href="{{ $linkTo($item) }}"
                            @class(['list-group-item', 'list-group-item-action', 'active' => $item['key'] === $current])
                            @if ($item['key'] === $current) aria-current="page" @endif
                        >{{ $item['label'] }}</a>
                    @elseif (isset($item['href']))
                        <a href="{{ $item['href'] }}" class="list-group-item list-group-item-action">{{ $item['label'] }}</a>
                    @else
                        <button
                            type="button"
                            class="list-group-item list-group-item-action"
                            :class="{ 'active': active === @js($item['key']) }"
                            :aria-current="active === @js($item['key']) ? 'page' : null"
                            @click="setActive(@js($item['key']))"
                        >{{ $item['label'] }}</button>
                    @endif
                @endforeach
            </div>
        @endforeach
    </nav>
</div>
