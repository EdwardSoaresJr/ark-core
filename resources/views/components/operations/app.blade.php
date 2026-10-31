@props(['title' => null, 'printing' => false, 'ui' => 'legacy'])

@php
use App\Ark\Operations\Communications\AdvisorCommsPressure;
use App\Ark\Operations\Communications\CommsChannelStripResolver;
use App\Ark\Operations\Communications\CommunicationsNavPressure;
use App\Ark\Operations\Communications\CommsPressureSettings;
use App\Ark\Operations\Staff\StaffNavigation;
use App\Ark\Operations\Telephony\IncomingCallBroadcast;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\DevRolePretend;

    $accentHtml = auth()->user()?->accentHtmlAttributes() ?? ['data-accent' => 'ark2'];
    $commsPressureSettings = CommsPressureSettings::fromShopSettings();
    $commsAccountabilityEnabled = $commsPressureSettings->attentionGateEnabled();
    // One attention cache key for rail badge + channel strip + Needs You list.
    $previousLastSeen = request()->attributes->get('operations.previous_last_seen_at');
    $previousLastSeenAt = $previousLastSeen instanceof \Illuminate\Support\Carbon
        ? $previousLastSeen
        : (is_string($previousLastSeen) ? \Illuminate\Support\Carbon::parse($previousLastSeen) : null);
    $commsPressure = auth()->user()?->can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
        ? app(AdvisorCommsPressure::class)->resolve(auth()->user(), $previousLastSeenAt)
        : ['count' => 0, 'summary' => [], 'attention_url' => \App\Ark\Operations\Communications\CommunicationsNeedsYou::url(), 'has_live_calls' => false];
    $commsNavPressure = auth()->user()?->can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
        ? app(CommunicationsNavPressure::class)->resolve(auth()->user(), $previousLastSeenAt)
        : ['nav_pressure_count' => 0, 'summary' => [], 'workboard_counts' => []];
    $commsNavCount = (int) ($commsNavPressure['nav_pressure_count'] ?? 0);
    $navSections = StaffNavigation::sections(auth()->user(), request(), $commsPressure['attention_url']);
    $commsNavSummary = is_array($commsNavPressure['summary'] ?? null) ? $commsNavPressure['summary'] : [];
    $commsNavPressureClass = $commsNavCount > 0
        ? match (true) {
            ($commsPressure['has_live_calls'] ?? false) => 'ops-rail-link--pressure-live',
            ($commsNavSummary['since_last_shift_count'] ?? 0) > 0 => 'ops-rail-link--pressure-shift',
            default => 'ops-rail-link--pressure',
        }
        : '';
    $canManageRepairOrders = auth()->user()?->can('repair_orders.manage') ?? false;
    $displayTheme = auth()->user()?->displayTheme()->value ?? 'system';
    $devRolePretendActive = DevRolePretend::isActive();
    $staffFrontDoorUrl = \App\Ark\Operations\Staff\StaffFrontDoor::landingUrl();
    $commsChannelTabs = app(CommsChannelStripResolver::class)->tabsFor(auth()->user(), $previousLastSeenAt);
    $showTopbarCommsStrip = $commsChannelTabs !== [] && ! request()->routeIs('operations.communications.*');
    $workstationPresence = WorkstationPresence::resolve(request());
    $accountMenu = auth()->user() ? StaffNavigation::accountMenu(auth()->user(), $workstationPresence) : [];
    // Tabler views still load app.css for the shell components that have not migrated. See docs/engineering/TABLER.md.
    $usesTabler = $ui === 'tabler';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" @foreach ($accentHtml as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
            <meta name="ark-comms-interrupt-url" content="{{ route('operations.comms.interrupts') }}">
            <meta name="ark-staff-presence-heartbeat-url" content="{{ route('operations.staff.presence.heartbeat') }}">
            <meta name="ark-incoming-call-dismiss-url" content="{{ route('operations.telephony.incoming-call.dismiss') }}">
            <meta name="ark-website-lead-interrupt-dismiss-url" content="{{ route('operations.leads.website-interrupt.dismiss') }}">
            <meta name="ark-portal-interrupt-dismiss-url" content="{{ route('operations.portal.customer-activity-interrupt.dismiss') }}">
            <meta name="ark-call-queue-url" content="{{ route('operations.telephony.call-queue') }}">
            <meta name="ark-call-queue-mark-worked-url" content="{{ str_replace('0', '__CALL_SESSION__', route('operations.telephony.call-queue.worked', ['callSession' => 0])) }}">
            <meta name="ark-call-queue-claim-url" content="{{ str_replace('0', '__CALL_SESSION__', route('operations.telephony.calls.claim', ['callSession' => 0])) }}">
            <meta name="ark-telephony-callback-url" content="{{ route('operations.telephony.callback') }}">
            <meta name="ark-comms-mark-read-url" content="{{ str_replace('0', '__CONVERSATION__', route('operations.conversations.read', ['conversation' => 0])) }}">
            <meta name="ark-current-user-id" content="{{ auth()->id() }}">
            <meta name="ark-comms-browser-notifications" content="{{ $commsPressureSettings->browserNotificationsEnabled() ? '1' : '0' }}">
            <meta name="ark-comms-attention-gate-enabled" content="{{ $commsAccountabilityEnabled ? '1' : '0' }}">
            <meta name="ark-owned-popup-timeout-seconds" content="{{ $commsPressureSettings->ownedPopupTimeoutSeconds() }}">
            <meta name="ark-workstation-privacy-active" content="0">
            @if (IncomingCallBroadcast::enabled() && filled(config('broadcasting.connections.reverb.key')))
                <meta name="ark-reverb-app-key" content="{{ config('broadcasting.connections.reverb.key') }}">
            @endif
        @endcan

        <title>{{ \App\Support\Branding\Branding::tabTitle() }}</title>

        @include('partials.branding._favicons')

        @fonts
        @stack('commercial-scripts')
        @include('partials.shop-channel')
        @if ($usesTabler)
            @vite(['resources/css/app.css', 'resources/css/tabler.css', 'resources/js/app.js', 'resources/js/tabler.js'])
        @else
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body @class([
        'min-h-screen overflow-x-hidden bg-slate-100 font-sans text-slate-950 antialiased dark:bg-slate-950 dark:text-slate-100' => ! $usesTabler,
    ])>
        @if (\App\Ark\Runtime\DemoInstall::isDemo())
            @include('demo.bar')
        @endif
        <script>
            (() => {
                const serverTheme = @json($displayTheme);
                const legacyTheme = localStorage.getItem('ark_theme') || localStorage.getItem('hs_theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const resolvedTheme = (() => {
                    if (serverTheme === 'dark') return 'dark';
                    if (serverTheme === 'light') return 'light';
                    if (serverTheme === 'system') {
                        return prefersDark ? 'dark' : 'light';
                    }

                    return legacyTheme === 'dark' || (!legacyTheme && prefersDark) ? 'dark' : 'light';
                })();

                const applyTheme = (theme) => {
                    const isDark = theme === 'dark';
                    document.documentElement.classList.toggle('dark', isDark);
                    document.documentElement.setAttribute('data-bs-theme', theme);
                    localStorage.setItem('ark_theme', theme);
                    localStorage.removeItem('hs_theme');
                };

                applyTheme(resolvedTheme);

                window.arkToggleTheme = () => {
                    const nextTheme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
                    applyTheme(nextTheme);

                    const token = document.querySelector('meta[name="csrf-token"]')?.content;
                    const url = @json(route('profile.display-theme.update'));

                    if (!token || !url) {
                        return;
                    }

                    fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({ display_theme: nextTheme }),
                        credentials: 'same-origin',
                    }).catch(() => {});
                };
            })();
        </script>

        @if ($usesTabler)
            @include('components.operations.shell.tabler')
        @else
        <div class="ops-shell min-h-screen" data-ops-mobile-nav>
            <div class="ops-mobile-nav-backdrop" data-ops-mobile-nav-backdrop hidden></div>
            <aside id="ops-mobile-nav" class="ops-left-rail" data-ops-mobile-nav-drawer>
                <div class="ops-rail-drawer-head">
                    <p class="ops-rail-drawer-head__label">Close</p>
                    <button type="button" class="ops-rail-drawer-head__close" data-ops-mobile-nav-close aria-label="Close navigation">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <a href="{{ $staffFrontDoorUrl }}" class="ops-rail-brand">
                    <span class="ops-rail-brand-logo-wrap">
                        <img
                            src="{{ \App\Support\Branding\Branding::sidebarLogo() }}"
                            alt="{{ \App\Support\Branding\Branding::tabTitle() }}"
                            class="ops-rail-brand-logo dark:hidden"
                            width="1432"
                            height="434"
                        >
                        <img
                            src="{{ \App\Support\Branding\Branding::logo('full_white') }}"
                            alt=""
                            class="ops-rail-brand-logo hidden dark:block"
                            width="1432"
                            height="434"
                        >
                    </span>
                </a>

                <nav aria-label="Primary operations" class="ops-rail-nav">
                    @foreach ($navSections as $section)
                        <div class="ops-rail-section">
                            @if ($section['label'] !== null)
                                <p class="ops-rail-section__label">{{ $section['label'] }}</p>
                            @endif
                            @foreach ($section['items'] as $item)
                                @if ($item['live_count'])
                                    <a
                                        href="{{ $item['url'] }}"
                                        data-ops-comms-nav-link
                                        data-initial-count="{{ $commsNavCount }}"
                                        class="ops-rail-link {{ $item['active'] ? 'ops-rail-link--active' : '' }} {{ $commsNavPressureClass }}"
                                    >
                                        <span class="ops-rail-icon">
                                            <x-operations.rail-icon :name="$item['key']" />
                                        </span>
                                        <span class="ops-rail-link__label">{{ $item['label'] }}</span>
                                        @if ($commsNavCount > 0)
                                            <span class="ops-rail-link__count" data-ops-comms-nav-count aria-label="{{ $commsNavCount }} need attention">({{ $commsNavCount }})</span>
                                        @endif
                                    </a>
                                @else
                                    <a href="{{ $item['url'] }}" class="ops-rail-link {{ $item['active'] ? 'ops-rail-link--active' : '' }}">
                                        <span class="ops-rail-icon">
                                            <x-operations.rail-icon :name="$item['key']" />
                                        </span>
                                        <span>{{ $item['label'] }}</span>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </nav>
                @auth
                    <form method="POST" action="{{ route('logout') }}" class="ops-rail-drawer-signout">
                        @csrf
                        <button type="submit">Sign out</button>
                    </form>
                @endauth
            </aside>

            <div class="ops-main-shell" data-ops-mobile-nav-main>
                <div class="ops-mobile-bar">
                    <a href="{{ $staffFrontDoorUrl }}" class="ops-mobile-bar__brand">
                        <img
                            src="{{ \App\Support\Branding\Branding::sidebarLogo() }}"
                            alt="{{ \App\Support\Branding\Branding::tabTitle() }}"
                            class="ops-mobile-bar__logo dark:hidden"
                            width="1432"
                            height="434"
                        >
                        <img
                            src="{{ \App\Support\Branding\Branding::logo('full_white') }}"
                            alt=""
                            class="ops-mobile-bar__logo hidden dark:block"
                            width="1432"
                            height="434"
                        >
                    </a>
                    <button
                        type="button"
                        class="ops-mobile-bar__menu"
                        data-ops-mobile-nav-open
                        aria-controls="ops-mobile-nav"
                        aria-expanded="false"
                        aria-label="Open navigation"
                    >
                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                            <path d="M3.5 5h13M3.5 10h13M3.5 15h13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
                <div class="ops-shell-chrome">
                    <header @class([
                        'ops-topbar',
                        'ops-topbar--with-comms' => $showTopbarCommsStrip,
                    ])>
                        @if ($showTopbarCommsStrip)
                            <div class="ops-topbar__comms">
                                @include('operations.communications.partials.channel-strip', [
                                    'comms_channel_tabs' => $commsChannelTabs,
                                    'variant' => 'topbar',
                                ])
                            </div>
                        @endif

                        <div @class([
                            'ops-topbar__account',
                            'ops-topbar__account--solo' => ! $showTopbarCommsStrip,
                        ])>
                            @php
                                $topbarAvatarUser = $workstationPresence->currentOperator ?? auth()->user();
                            @endphp
                            <x-operations.workstation-presence :presence="$workstationPresence" />
                            <x-operations.user-menu
                                :entries="$accountMenu"
                                :dev-role-pretend-active="$devRolePretendActive"
                                :avatar-initials="$topbarAvatarUser?->presenceAvatarInitials()"
                                :avatar-color="$topbarAvatarUser?->presenceAvatarColor()"
                            />
                        </div>
                    </header>

                @include('components.operations.workspace-tabs')
                </div>

                @can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
                    @if ($commsAccountabilityEnabled && $commsNavCount > 0 && ! request()->routeIs('operations.index', 'operations.communications.attention', 'operations.communications.inbox', 'operations.communications.history', 'operations.communications.calls', 'operations.communications.internal', 'operations.communications.internal.channel'))
                        <x-operations.comms-pressure-bar :pressure="[
                            'count' => $commsNavCount,
                            'summary' => $commsNavSummary,
                            'attention_url' => \App\Ark\Operations\Communications\CommunicationsNeedsYou::url(),
                            'has_live_calls' => (bool) ($commsNavSummary['has_live_calls'] ?? false),
                        ]" />
                    @endif
                @endcan

                <main class="ops-main">
                    @if ($learnTrainingSnooze)
                        <div class="ops-learn-snooze-banner ops-learn-snooze-banner--workspace" role="status">
                            <span>
                                Required training snoozed until <strong>{{ $learnTrainingSnooze['snoozed_until_label'] }}</strong>.
                            </span>
                            <a href="{{ \App\Ark\Operations\Learn\ArkademyUrls::staffNavUrl() }}">Resume guides</a>
                            @if ($canSnoozeTraining ?? false)
                                <form method="POST" action="{{ route('operations.learn.progress.snooze') }}" class="ops-learn-snooze-form ops-learn-snooze-form--inline">
                                    @csrf
                                    <button type="submit" class="ops-learn-snooze-form__btn ops-learn-snooze-form__btn--compact">
                                        Snooze {{ \App\Ark\Operations\Learn\LearnArkCurriculum::SNOOZE_HOURS }}h more
                                    </button>
                                </form>
                            @endif
                        </div>
                    @elseif (session('learn_snoozed'))
                        <div class="ops-learn-snooze-banner ops-learn-snooze-banner--workspace" role="status">
                            <span>
                                Training snoozed for {{ session('learn_snoozed.hours') }} hours - back at <strong>{{ session('learn_snoozed.until_label') }}</strong>.
                            </span>
                            <a href="{{ \App\Ark\Operations\Learn\ArkademyUrls::staffNavUrl() }}">Resume guides</a>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
        @endif

        @if ($usesTabler)
            <div class="ark-legacy">
        @endif

        @stack('ops-ro-orientation-dock')

        @can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
            <x-operations.comms-interrupt-panel />
            <x-operations.global-search />
        @endcan
        <x-operations.image-lightbox />
        @can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
            <x-operations.learn.guide-modal />
        @endcan

        @if ($printing)
            @include('components.operations.print-helpers')
        @endif

        @can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
            <x-operations.call-queue :poller-only="true" />
        @endcan

        @if ($usesTabler)
            </div>
        @endif
    </body>
</html>
