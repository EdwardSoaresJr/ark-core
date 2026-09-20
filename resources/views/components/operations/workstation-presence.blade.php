@props(['presence'])

@php
    use App\Ark\Operations\Workstations\Workstation;

    /** @var \App\Ark\Operations\Workstations\WorkstationPresence $presence */
    $workstations = Workstation::query()
        ->with('primaryTelephonyExtension')
        ->where('is_active', true)
        ->orderBy('name')
        ->get();
    $wsLogoUrl = \App\Support\Branding\Branding::logo('full_white');
    $workstationPresenceSettings = \App\Ark\Operations\Workstations\WorkstationPresenceSettings::fromShopSettings();
    $idleLockMinutes = $workstationPresenceSettings->idleLockMinutes();
    $stationOperatorActive = $presence->workstation !== null
        && $presence->currentOperator !== null
        && ! $presence->locked
        && ! $presence->needsPinSetup;
@endphp

@if ($presence->canManagePresence)
    <div
        class="ws-presence"
        data-ark-workstation-presence
        data-locked="{{ $presence->locked ? '1' : '0' }}"
        data-needs-binding="{{ $presence->needsBinding ? '1' : '0' }}"
        data-needs-unlock="{{ $presence->needsUnlock ? '1' : '0' }}"
        data-needs-pin-setup="{{ $presence->needsPinSetup ? '1' : '0' }}"
        data-staff-url="{{ route('operations.workstation.staff') }}"
        data-unlock-url="{{ route('operations.workstation.unlock') }}"
        data-pin-store-url="{{ route('operations.workstation.pin.store') }}"
        data-pin-update-url="{{ route('operations.workstation.pin.update') }}"
        data-current-user-id="{{ auth()->id() }}"
        data-lock-url="{{ route('operations.workstation.lock') }}"
        data-bind-url="{{ route('operations.workstation.bind') }}"
        data-bind-dismiss-url="{{ route('operations.workstation.bind.dismiss') }}"
        data-idle-lock-minutes="{{ $idleLockMinutes }}"
        data-station-operator-active="{{ $stationOperatorActive ? '1' : '0' }}"
    >
        @if ($presence->workstation)
            <div class="ws-presence-topbar">
                <span class="ws-presence-topbar__station">{{ $presence->workstation->applianceStationLabel() }}</span>
            </div>
        @endif

        @if ($presence->needsBinding)
            <div class="ws-presence-bind" data-ws-bind-panel>
                <x-operations.partials.workstation-presence-backdrop />
                <div class="ws-presence-overlay__panel ws-presence-bind__card">
                    <div class="ws-presence-overlay__panel-accent" aria-hidden="true"></div>
                    <header class="ws-presence-overlay__brand">
                        <img src="{{ $wsLogoUrl }}" alt="ARK" class="ws-presence-overlay__brand-logo">
                    </header>
                    <p class="ws-presence-bind__title">Where are you working?</p>
                    <p class="ws-presence-overlay__hint">Choose the station for this browser.</p>
                    <form data-ws-bind-form class="ws-presence-bind__form">
                        @csrf
                        <select name="workstation_id" required class="ws-presence-bind__select">
                            <option value="">Choose station…</option>
                            @foreach ($workstations as $workstation)
                                <option value="{{ $workstation->id }}">
                                    {{ $workstation->applianceStationLabel() }}
                                </option>
                            @endforeach
                        </select>
                        <div class="ws-presence-bind__actions">
                            <button type="submit" class="ws-presence-bar__btn ws-presence-bar__btn--primary ws-presence-bar__btn--ark">Continue</button>
                            <button type="button" class="ws-presence-bar__btn" data-ws-action="dismiss-bind">Not now</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="ws-presence-overlay" data-ws-overlay="change-pin" hidden>
            <x-operations.partials.workstation-presence-backdrop />
            <div class="ws-presence-overlay__panel">
                <div class="ws-presence-overlay__panel-accent" aria-hidden="true"></div>
                <header class="ws-presence-overlay__brand">
                    <img src="{{ $wsLogoUrl }}" alt="ARK" class="ws-presence-overlay__brand-logo">
                    <button type="button" class="ws-presence-overlay__close" data-ws-action="close-overlay" aria-label="Close">×</button>
                </header>

                <div class="ws-presence-overlay__hero">
                    <p class="ws-presence-overlay__station-name">{{ auth()->user()?->name }}</p>
                    <p class="ws-presence-overlay__kicker">Change station PIN</p>
                </div>

                <p class="ws-presence-overlay__hint">Choose a new 4-digit PIN. Confirm with your ARK password.</p>

                <form data-ws-change-pin-form class="ws-presence-pin-setup space-y-3" autocomplete="off">
                    @csrf
                    <label class="block">
                        <span class="text-xs font-semibold text-slate-700">Current password</span>
                        <input
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            class="mt-1 h-12 w-full rounded-sm border border-slate-300 px-3 text-base"
                        >
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-slate-700">New PIN</span>
                        <input
                            type="tel"
                            name="pin"
                            required
                            maxlength="4"
                            pattern="\d{4}"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            data-ws-pin-field
                            data-lpignore="true"
                            data-1p-ignore
                            class="mt-1 h-12 w-full rounded-sm border border-slate-300 px-3 font-mono text-base tracking-widest"
                        >
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-slate-700">Confirm PIN</span>
                        <input
                            type="tel"
                            name="pin_confirmation"
                            required
                            maxlength="4"
                            pattern="\d{4}"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            data-ws-pin-field
                            data-lpignore="true"
                            data-1p-ignore
                            class="mt-1 h-12 w-full rounded-sm border border-slate-300 px-3 font-mono text-base tracking-widest"
                        >
                    </label>
                    <p data-ws-change-pin-error class="ws-presence-pin__error" hidden></p>
                    <button type="submit" class="ws-presence-bar__btn ws-presence-bar__btn--primary ws-presence-bar__btn--ark w-full">Save PIN</button>
                </form>
            </div>
        </div>

        <div class="ws-presence-overlay" data-ws-overlay="switch-station" hidden>
            <x-operations.partials.workstation-presence-backdrop />
            <div class="ws-presence-overlay__panel">
                <div class="ws-presence-overlay__panel-accent" aria-hidden="true"></div>
                <header class="ws-presence-overlay__brand">
                    <img src="{{ $wsLogoUrl }}" alt="ARK" class="ws-presence-overlay__brand-logo">
                    <button type="button" class="ws-presence-overlay__close" data-ws-action="close-overlay" aria-label="Close">×</button>
                </header>
                <div class="ws-presence-overlay__hero">
                    <p class="ws-presence-overlay__station-name">Switch station</p>
                    <p class="ws-presence-overlay__kicker">Station presence</p>
                </div>
                <p class="ws-presence-overlay__hint">Where are you working?</p>
                <form data-ws-switch-station-form class="ws-presence-bind__form">
                    @csrf
                    <ul class="ws-presence-station-list">
                        @foreach ($workstations as $workstation)
                            <li>
                                <label class="ws-presence-station-option">
                                    <input
                                        type="radio"
                                        name="workstation_id"
                                        value="{{ $workstation->id }}"
                                        @checked($presence->workstation?->id === $workstation->id)
                                    >
                                    <span>{{ $workstation->applianceStationLabel() }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                    <button type="submit" class="ws-presence-bar__btn ws-presence-bar__btn--primary ws-presence-bar__btn--ark mt-3 w-full">Switch station</button>
                </form>
            </div>
        </div>
    </div>

    <style>
        .ws-presence {
            --ws-presence-accent-500: var(--ops-accent-500);
            --ws-presence-accent-400: color-mix(in srgb, var(--ws-presence-accent-500) 68%, white);
            --ws-presence-accent-800: color-mix(in srgb, var(--ws-presence-accent-500) 72%, black);
            --ws-presence-accent-900: color-mix(in srgb, var(--ws-presence-accent-500) 58%, black);
            display: flex;
            align-items: center;
            margin-right: 0;
        }

        .ws-presence-topbar {
            display: flex;
            align-items: center;
        }

        .ws-presence-topbar__station {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: rgb(100 116 139);
            white-space: nowrap;
        }

        .ws-presence-topbar__action {
            min-height: 2rem;
            border: 1px solid rgb(203 213 225);
            border-radius: 0.25rem;
            background: #fff;
            padding: 0 0.75rem;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: rgb(51 65 85);
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            white-space: nowrap;
        }

        .ws-presence-topbar__action:active {
            background: rgb(241 245 249);
        }

        .ws-presence-topbar__action--primary {
            background: linear-gradient(180deg, var(--ws-presence-accent-500) 0%, var(--ws-presence-accent-800) 100%);
            color: #fff;
            border-color: var(--ws-presence-accent-800);
        }

        .ws-presence-topbar__action--primary:active {
            background: var(--ws-presence-accent-900);
        }

        .ws-presence-bar__btn {
            border: 1px solid rgb(203 213 225);
            background: #fff;
            border-radius: 0.125rem;
            padding: 0.5rem 0.75rem;
            min-height: 2.75rem;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: rgb(51 65 85);
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }

        .ws-presence-bar__btn:active {
            background: rgb(241 245 249);
        }

        .ws-presence-bar__btn--primary {
            background: rgb(15 23 42);
            border-color: rgb(15 23 42);
            color: #fff;
        }

        .ws-presence-bind {
            position: fixed;
            inset: 0;
            z-index: 145;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .ws-presence--bind .ws-presence-bind {
            display: flex;
        }

        .ws-presence-backdrop {
            position: absolute;
            inset: 0;
            overflow: hidden;
            background: #0a1628;
        }

        .ws-presence-backdrop__gradient {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 15% 0%, color-mix(in srgb, var(--ws-presence-accent-500) 45%, transparent) 0%, transparent 55%),
                radial-gradient(ellipse 70% 50% at 100% 100%, color-mix(in srgb, var(--ws-presence-accent-800) 35%, transparent) 0%, transparent 50%),
                linear-gradient(
                    155deg,
                    #0a1628 0%,
                    color-mix(in srgb, var(--ws-presence-accent-900) 55%, #0a1628) 45%,
                    color-mix(in srgb, var(--ws-presence-accent-800) 40%, #0b3d5c) 100%
                );
        }

        .ws-presence-backdrop__pattern {
            position: absolute;
            inset: 0;
            opacity: 0.22;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.04) 1px, transparent 1px);
            background-size: 48px 48px;
        }

        .ws-presence-backdrop__wordmark {
            position: absolute;
            left: 50%;
            top: 42%;
            transform: translate(-50%, -50%);
            width: min(72vw, 520px);
            opacity: 0.04;
            pointer-events: none;
        }

        .ws-presence-bind__card {
            position: relative;
            z-index: 1;
        }

        .ws-presence-bind__title {
            font-size: 18px;
            font-weight: 900;
            color: rgb(15 23 42);
            margin-bottom: 0.35rem;
        }

        .ws-presence-bind__select {
            width: 100%;
            border: 1px solid rgb(203 213 225);
            border-radius: 0.25rem;
            padding: 0.75rem;
            min-height: 3rem;
            font-size: 16px;
            background: #fff;
        }

        .ws-presence-bind__actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .ws-presence-overlay {
            position: fixed;
            inset: 0;
            z-index: 145;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .ws-presence--open .ws-presence-overlay:not([hidden]) {
            display: flex;
        }

        .ws-presence-overlay__panel {
            position: relative;
            z-index: 1;
            width: min(26rem, calc(100vw - 2rem));
            background: #fff;
            border-radius: 0.375rem;
            padding: 1.25rem 1.35rem 1.35rem;
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.08),
                0 28px 56px rgba(0, 0, 0, 0.45);
        }

        .ws-presence-overlay__panel-accent {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            border-radius: 0.375rem 0.375rem 0 0;
            background: linear-gradient(90deg, var(--ws-presence-accent-500) 0%, var(--ws-presence-accent-800) 100%);
        }

        .ws-presence-overlay__brand {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            margin-bottom: 0.85rem;
        }

        .ws-presence-overlay__brand-logo {
            height: 26px;
            width: auto;
            max-width: 100%;
        }

        .ws-presence-overlay__close {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 2.75rem;
            min-height: 2.75rem;
            border: 1px solid rgb(226 232 240);
            border-radius: 0.25rem;
            background: rgb(248 250 252);
            font-size: 22px;
            line-height: 1;
            color: rgb(71 85 105);
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }

        .ws-presence-overlay__close:active {
            background: rgb(241 245 249);
        }

        .ws-presence-overlay__hero {
            text-align: center;
            margin-bottom: 0.85rem;
            padding-bottom: 0.85rem;
            border-bottom: 1px solid rgb(226 232 240);
        }

        .ws-presence-overlay__station-name {
            font-size: 22px;
            font-weight: 900;
            line-height: 1.15;
            color: rgb(15 23 42);
        }

        .ws-presence-overlay__kicker {
            margin-top: 0.3rem;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--ws-presence-accent-500);
        }

        .ws-presence-overlay__hint {
            font-size: 13px;
            line-height: 1.45;
            color: rgb(71 85 105);
            margin-bottom: 0.75rem;
        }

        .ws-presence-section-label {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgb(100 116 139);
            margin-bottom: 0.4rem;
        }

        .ws-presence-section-label--center {
            text-align: center;
            margin-top: 0.5rem;
        }

        .ws-presence-overlay__panel--unlock {
            width: min(40rem, calc(100vw - 2rem));
        }

        .ws-presence-unlock__body {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 11.75rem;
            gap: 1.25rem;
            align-items: start;
        }

        .ws-presence-unlock__operators {
            min-width: 0;
        }

        .ws-presence-unlock__pin {
            padding-top: 0.15rem;
        }

        .ws-presence-staff-search {
            display: block;
            margin-bottom: 0.65rem;
        }

        .ws-presence-staff-search__input {
            display: block;
            width: 100%;
            margin-top: 0.35rem;
            min-height: 3rem;
            border: 1px solid rgb(203 213 225);
            border-radius: 0.25rem;
            padding: 0.65rem 0.75rem;
            font-size: 16px;
            background: #fff;
            touch-action: manipulation;
        }

        .ws-presence-staff-search__input:focus {
            outline: none;
            border-color: var(--ws-presence-accent-500);
            box-shadow: 0 0 0 1px var(--ws-presence-accent-500);
        }

        .ws-presence-staff-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(5.5rem, 1fr));
            gap: 0.5rem;
            max-height: min(15rem, 34vh);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding: 0.1rem;
        }

        .ws-presence-staff-empty {
            font-size: 12px;
            font-weight: 600;
            color: rgb(100 116 139);
            text-align: center;
            padding: 0.75rem 0.5rem;
        }

        .ws-presence-staff-tile {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            gap: 0.4rem;
            padding: 0.55rem 0.35rem 0.5rem;
            min-height: 5.75rem;
            border: 2px solid rgb(226 232 240);
            border-radius: 0.375rem;
            background: rgb(248 250 252);
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }

        .ws-presence-staff-tile:active {
            background: rgb(241 245 249);
        }

        .ws-presence-staff-tile--selected {
            border-color: var(--ws-presence-accent-500);
            background: color-mix(in srgb, var(--ws-presence-accent-500) 9%, white);
            box-shadow: 0 0 0 1px var(--ws-presence-accent-500);
        }

        .ws-presence-staff-tile--no-pin {
            opacity: 0.55;
        }

        .ws-presence-staff-tile__avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 3.25rem;
            height: 3.25rem;
            border-radius: 0.375rem;
            font-size: 1rem;
            font-weight: 900;
            letter-spacing: 0.02em;
            color: #fff;
            flex-shrink: 0;
            box-shadow: inset 0 -2px 0 rgba(0, 0, 0, 0.12);
        }

        .ws-presence-staff-tile__name {
            font-size: 11px;
            font-weight: 700;
            line-height: 1.25;
            text-align: center;
            color: rgb(15 23 42);
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
        }

        @media (max-width: 540px) {
            .ws-presence-unlock__body {
                grid-template-columns: 1fr;
            }

            .ws-presence-unlock__pin {
                padding-top: 0;
            }
        }

        .ws-presence-station-list {
            display: grid;
            gap: 0.4rem;
            margin-bottom: 0.75rem;
        }

        .ws-presence-station-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-align: left;
            border: 1px solid rgb(226 232 240);
            background: rgb(248 250 252);
            border-radius: 0.25rem;
            padding: 0.75rem 0.85rem;
            min-height: 3rem;
            font-size: 15px;
            font-weight: 700;
            color: rgb(15 23 42);
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            width: 100%;
        }

        .ws-presence-pin {
            position: relative;
        }

        .ws-presence-pin__entry {
            position: relative;
            min-height: 3rem;
            margin-bottom: 0.35rem;
        }

        .ws-presence-pin__capture {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            border: 0;
            background: transparent;
            caret-color: transparent;
            z-index: 2;
            font-size: 16px;
            touch-action: manipulation;
        }

        .ws-presence-pin__capture:focus {
            outline: none;
        }

        .ws-presence-pin__dots {
            position: relative;
            z-index: 1;
            pointer-events: none;
            text-align: center;
            font-size: 34px;
            line-height: 3rem;
            letter-spacing: 0.4em;
            color: rgb(15 23 42);
        }

        .ws-presence-pin-setup input[data-ws-pin-field] {
            -webkit-text-security: disc;
        }

        .ws-presence-pin__error {
            text-align: center;
            font-size: 12px;
            font-weight: 700;
            color: rgb(190 18 60);
            margin-bottom: 0.5rem;
        }

        .ws-presence-pin__pad {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.5rem;
        }

        .ws-presence-pin__digit {
            min-height: 3rem;
            border: 1px solid rgb(203 213 225);
            border-radius: 0.25rem;
            background: rgb(248 250 252);
            font-size: 22px;
            font-weight: 800;
            color: rgb(15 23 42);
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }

        .ws-presence-pin__digit--muted {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: rgb(71 85 105);
        }

        .ws-presence-pin__digit[data-ws-action="back-pin"] {
            font-size: 26px;
            line-height: 1;
            text-transform: none;
            letter-spacing: 0;
        }

        .ws-presence-pin__digit:active {
            background: rgb(226 232 240);
            border-color: rgb(148 163 184);
        }

        .ws-presence-bar__btn--ark {
            background: linear-gradient(180deg, var(--ws-presence-accent-500) 0%, var(--ws-presence-accent-800) 100%);
            border-color: var(--ws-presence-accent-800);
            color: #fff;
        }

        .ws-presence-bar__btn--ark:active {
            background: var(--ws-presence-accent-900);
        }
    </style>
@endif
