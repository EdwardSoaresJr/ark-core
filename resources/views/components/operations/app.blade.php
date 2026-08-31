@props(['title' => null, 'printing' => false])

@php
use App\Ark\Operations\Communications\AdvisorCommsPressure;
use App\Ark\Operations\Communications\CommsChannelStripResolver;
use App\Ark\Operations\Communications\CommunicationsNavPressure;
use App\Ark\Operations\Communications\CommsPressureSettings;
use App\Ark\Operations\Telephony\IncomingCallBroadcast;
use App\Ark\Operations\Telephony\TelephonyCallFlowSettings;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\DevRolePretend;

    $accentHtml = auth()->user()?->accentHtmlAttributes() ?? ['data-accent' => 'ark2'];
    $canAccessOwnerWorkspace = auth()->user()?->canAccessOwnerWorkspace() ?? false;
    $canUseTimeClock = \App\Ark\Operations\Labor\TechnicianTimeClockProjection::canAccess(auth()->user());
    $canAccessBusinessWorkspace = \App\Ark\Operations\Business\BusinessWorkspaceAccess::allows(auth()->user());
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
    $devRolePretendVisible = DevRolePretend::switcherVisible(auth()->user());
    $devRolePretendActive = DevRolePretend::isActive();
    $staffFrontDoorUrl = \App\Ark\Operations\Staff\StaffFrontDoor::landingUrl();
    $usesAdvisorWorkSurface = \App\Ark\Operations\Staff\StaffFrontDoor::usesAdvisorWorkSurface();
    $canUseProductionShell = auth()->user()?->can(App\Ark\Runtime\Authorization\ArkCapability::ProductionAccess->value) ?? false;
    $hasOperationsNav = auth()->user()?->can('operations.access')
        || ($canUseProductionShell && ! auth()->user()?->can('operations.access'));
    $hasRecordsNav = (auth()->user()?->can('customers.manage') ?? false)
        || (auth()->user()?->can('repair_orders.view') ?? false);
    $hasBusinessNav = $canAccessBusinessWorkspace
        || auth()->user()?->can('settings.manage');
    $hasPlatformNav = ($canUseProductionShell || $usesAdvisorWorkSurface)
        || auth()->user()?->can('settings.manage');
    $hasSystemNav = auth()->user()?->can('settings.manage') ?? false;
    $commsChannelTabs = app(CommsChannelStripResolver::class)->tabsFor(auth()->user(), $previousLastSeenAt);
    $showTopbarCommsStrip = $commsChannelTabs !== [];
    $workstationPresence = WorkstationPresence::resolve(request());
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
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen overflow-x-hidden bg-slate-100 font-sans text-slate-950 antialiased dark:bg-slate-950 dark:text-slate-100">
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

                    // No server preference and no legacy storage: light (not OS dark).
                    return legacyTheme === 'dark' ? 'dark' : 'light';
                })();

                const applyTheme = (theme) => {
                    const isDark = theme === 'dark';
                    document.documentElement.classList.toggle('dark', isDark);
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

        <div class="ops-shell min-h-screen">
            <aside class="ops-left-rail">
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
                    @if ($canUseProductionShell)
                        <div class="ops-rail-section">
                            <a href="{{ route('operations.today') }}" class="ops-rail-link {{ request()->routeIs('operations.today', 'operations.briefing') ? 'ops-rail-link--active' : '' }}">
                                <span class="ops-rail-icon">
                                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                        <path d="M4 4.5h12v11H4v-11z" stroke="currentColor" stroke-width="1.4" />
                                        <path d="M7 8h6M7 11h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                    </svg>
                                </span>
                                <span>Today</span>
                            </a>
                            @if ($canUseTimeClock)
                                <a href="{{ route('operations.time-clock.index') }}" class="ops-rail-link {{ request()->routeIs('operations.time-clock.*') ? 'ops-rail-link--active' : '' }}">
                                    <span class="ops-rail-icon">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                            <circle cx="10" cy="10" r="6.5" stroke="currentColor" stroke-width="1.4" />
                                            <path d="M10 6.5V10l2.5 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                        </svg>
                                    </span>
                                    <span>Time clock</span>
                                </a>
                            @endif
                        </div>
                    @endif

                    @if ($hasOperationsNav)
                        <div class="ops-rail-section">
                            <p class="ops-rail-section__label">Operations</p>
                            @can('operations.access')
                                <a href="{{ route('operations.index') }}" class="ops-rail-link {{ request()->routeIs('operations.index', 'operations.workboard') ? 'ops-rail-link--active' : '' }}">
                                    <span class="ops-rail-icon">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                            <path d="M3 4h6v5H3V4zM11 4h6v5h-6V4zM3 11h6v5H3v-5zM11 11h6v5h-6v-5z" stroke="currentColor" stroke-width="1.4" />
                                        </svg>
                                    </span>
                                    <span>Job Board</span>
                                </a>
                            @endcan
                            @if ($canUseProductionShell && ! auth()->user()?->can('operations.access'))
                                <a href="{{ route('operations.workboard') }}" class="ops-rail-link {{ request()->routeIs('operations.workboard') ? 'ops-rail-link--active' : '' }}">
                                    <span class="ops-rail-icon">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                            <path d="M3 4h6v5H3V4zM11 4h6v5h-6V4zM3 11h6v5H3v-5zM11 11h6v5h-6v-5z" stroke="currentColor" stroke-width="1.4" />
                                        </svg>
                                    </span>
                                    <span>Workboard</span>
                                </a>
                            @endif
                            @can('operations.access')
                                @if (\App\Ark\Operations\Settings\ShopSettings::current()->appointmentsEnabled())
                                    <a href="{{ route('operations.appointments.index') }}" class="ops-rail-link {{ request()->routeIs('operations.appointments.*') ? 'ops-rail-link--active' : '' }}">
                                        <span class="ops-rail-icon">
                                            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                                <rect x="3.5" y="4.5" width="13" height="12" rx="0.5" stroke="currentColor" stroke-width="1.4" />
                                                <path d="M7 3v3M13 3v3M3.5 9h13" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                            </svg>
                                        </span>
                                        <span>Schedule</span>
                                    </a>
                                @endif
                            @endcan
                            @can('operations.access')
                                <a
                                    href="{{ \App\Ark\Operations\Communications\CommunicationsNeedsYou::url() }}"
                                    data-ops-comms-nav-link
                                    data-initial-count="{{ $commsNavCount }}"
                                    class="ops-rail-link {{ request()->routeIs('operations.communications.*') ? 'ops-rail-link--active' : '' }} {{ $commsNavPressureClass }}"
                                >
                                    <span class="ops-rail-icon">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                            <path d="M3.5 4.5h13v9h-13v-9z" stroke="currentColor" stroke-width="1.4" />
                                            <path d="M7.5 8.5h5M7.5 11h3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                        </svg>
                                    </span>
                                    <span class="ops-rail-link__label">Communications</span>
                                    @if ($commsNavCount > 0)
                                        <span class="ops-rail-link__count" data-ops-comms-nav-count aria-label="{{ $commsNavCount }} need attention">({{ $commsNavCount }})</span>
                                    @endif
                                </a>
                            @endcan
                        </div>
                    @endif

                    @if ($hasRecordsNav)
                        <div class="ops-rail-section">
                            <p class="ops-rail-section__label">Records</p>
                            @can('repair_orders.view')
                                <a href="{{ route('operations.repair-orders.index') }}" class="ops-rail-link {{ request()->routeIs('operations.repair-orders.*') ? 'ops-rail-link--active' : '' }}">
                                    <span class="ops-rail-icon">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                            <path d="M5 2.5h7l3 3V17a.5.5 0 01-.5.5h-9A.5.5 0 015 17V2.5z" stroke="currentColor" stroke-width="1.4" />
                                            <path d="M12 2.5V6h3M7.5 9h5M7.5 12h5M7.5 15h3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                        </svg>
                                    </span>
                                    <span>Repair Orders</span>
                                </a>
                            @endcan
                            @can('customers.manage')
                                <a href="{{ route('operations.vehicles.search') }}" class="ops-rail-link {{ request()->routeIs('operations.vehicles.*') ? 'ops-rail-link--active' : '' }}">
                                    <span class="ops-rail-icon">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                            <path d="M3.5 5.5h13v9.5h-13V5.5z" stroke="currentColor" stroke-width="1.4" />
                                            <path d="M3.5 8.5h13M6 11.5h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                            <circle cx="6.25" cy="13.75" r="1.25" fill="currentColor" />
                                            <circle cx="13.75" cy="13.75" r="1.25" fill="currentColor" />
                                        </svg>
                                    </span>
                                    <span>Vehicles</span>
                                </a>
                                <a href="{{ route('operations.customers.search') }}" class="ops-rail-link {{ request()->routeIs('operations.customers.*') ? 'ops-rail-link--active' : '' }}">
                                    <span class="ops-rail-icon">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                            <path d="M7.5 9a3 3 0 116 0 3 3 0 01-6 0z" stroke="currentColor" stroke-width="1.4" />
                                            <path d="M3.5 17c.85-2.7 3.1-4.25 6.5-4.25S15.65 14.3 16.5 17" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                        </svg>
                                    </span>
                                    <span>Customers</span>
                                </a>
                            @endcan
                        </div>
                    @endif

                    @if ($hasBusinessNav)
                        <div class="ops-rail-section">
                            <p class="ops-rail-section__label">Business</p>
                            @if ($canAccessBusinessWorkspace)
                                <a href="{{ route('operations.business') }}" class="ops-rail-link {{ request()->routeIs('operations.business') ? 'ops-rail-link--active' : '' }}">
                                    <span class="ops-rail-icon">
                                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" class="h-4 w-4">
                                            <path d="M3 4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4Zm8 0a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V4ZM3 12a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-4Zm8-1a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1h-4Z" />
                                        </svg>
                                    </span>
                                    <span>Cockpit</span>
                                </a>
                            @endif
                            @can(App\Ark\Runtime\Authorization\ArkCapability::GrowthAccess->value)
                                <a href="{{ route('growth.opportunities.index') }}" class="ops-rail-link {{ request()->routeIs('growth.*') ? 'ops-rail-link--active' : '' }}">
                                    <span class="ops-rail-icon">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                            <path d="M3.5 15.5c2-4 4.5-7 6.5-9s4-3.5 6.5-4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                            <path d="M3.5 15.5h13" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                            <circle cx="6" cy="11" r="1" fill="currentColor" />
                                            <circle cx="10" cy="8" r="1" fill="currentColor" />
                                            <circle cx="14" cy="5" r="1" fill="currentColor" />
                                        </svg>
                                    </span>
                                    <span>Growth</span>
                                </a>
                            @endcan
                            @can('settings.manage')
                                <a href="{{ route('website.performance') }}" class="ops-rail-link {{ request()->routeIs('website.*') ? 'ops-rail-link--active' : '' }}">
                                    <span class="ops-rail-icon">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                            <path d="M3.5 5.5h13v9h-13v-9z" stroke="currentColor" stroke-width="1.4" />
                                            <path d="M3.5 8.5h13" stroke="currentColor" stroke-width="1.4" />
                                            <path d="M7 12h6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                        </svg>
                                    </span>
                                    <span>Website</span>
                                </a>
                            @endcan
                            @can('financial.view')
                                <a href="{{ route('operations.reports.index') }}" class="ops-rail-link {{ request()->routeIs('operations.reports.*') ? 'ops-rail-link--active' : '' }}">
                                    <span class="ops-rail-icon">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                            <path d="M4 15.5h12M5.5 13V8M10 13V4.5M14.5 13v-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                        </svg>
                                    </span>
                                    <span>Reports</span>
                                </a>
                            @endcan
                            @if ($canAccessOwnerWorkspace)
                                <a href="{{ route('operations.owner.day-review') }}" class="ops-rail-link {{ request()->routeIs('operations.owner.day-review', 'operations.owner.bookend') ? 'ops-rail-link--active' : '' }}">
                                    <span class="ops-rail-icon">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                            <path d="M4 15.5h12M5.5 13V8M10 13V4.5M14.5 13v-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                            <path d="M3.5 3.5l13 13" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity="0.35" />
                                        </svg>
                                    </span>
                                    <span>Day Review</span>
                                </a>
                                <a href="{{ route('operations.owner.technician-production.index') }}" class="ops-rail-link {{ request()->routeIs('operations.owner.technician-production.*') ? 'ops-rail-link--active' : '' }}">
                                    <span class="ops-rail-icon">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                            <path d="M4 14.5h12M6 14.5V8.5M10 14.5V5.5M14 14.5V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                                        </svg>
                                    </span>
                                    <span>Tech production</span>
                                </a>
                            @endif
                        </div>
                    @endif

                    @if ($hasPlatformNav)
                        <div class="ops-rail-section">
                            <p class="ops-rail-section__label">Platform</p>
                            @if ($canUseProductionShell || $usesAdvisorWorkSurface)
                                <a href="{{ \App\Ark\Operations\Learn\ArkademyUrls::staffNavUrl() }}" class="ops-rail-link {{ ! \App\Ark\Operations\Learn\ArkademyUrls::isCutover() && request()->routeIs('operations.learn.*') ? 'ops-rail-link--active' : '' }}">
                                    <span class="ops-rail-icon">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                            <path d="M3.5 4.5h13v11h-13v-11z" stroke="currentColor" stroke-width="1.4" />
                                            <path d="M6.5 8.5h7M6.5 11h5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                            <path d="M10 2.5v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                        </svg>
                                    </span>
                                    <span>{{ \App\Support\Branding\Branding::learnName() }}</span>
                                </a>
                            @endif
                            @can('settings.manage')
                                <a href="{{ route('operations.shop.communications') }}" class="ops-rail-link {{ request()->routeIs('operations.shop.*') ? 'ops-rail-link--active' : '' }}">
                                    <span class="ops-rail-icon">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                            <path d="M4 4.5h12v11H4v-11z" stroke="currentColor" stroke-width="1.4" />
                                            <path d="M7 8h6M7 11h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                        </svg>
                                    </span>
                                    <span>Voice</span>
                                </a>
                            @endcan
                        </div>
                    @endif

                    @if ($hasSystemNav)
                        <div class="ops-rail-section">
                            <p class="ops-rail-section__label">System</p>
                            @can('settings.manage')
                                <a href="{{ route('operations.settings.shop.edit') }}" class="ops-rail-link {{ request()->routeIs('operations.settings.*') ? 'ops-rail-link--active' : '' }}">
                                    <span class="ops-rail-icon">
                                        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none">
                                            <path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" stroke="currentColor" stroke-width="1.4" />
                                            <path d="M10 2.5v2M10 15.5v2M4.7 4.7l1.4 1.4M13.9 13.9l1.4 1.4M2.5 10h2M15.5 10h2M4.7 15.3l1.4-1.4M13.9 6.1l1.4-1.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                                        </svg>
                                    </span>
                                    <span>Settings</span>
                                </a>
                            @endcan
                        </div>
                    @endif
                </nav>

            </aside>

            <div class="ops-main-shell">
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
                                :dev-role-pretend-visible="$devRolePretendVisible"
                                :dev-role-pretend-active="$devRolePretendActive"
                                :avatar-initials="$topbarAvatarUser?->presenceAvatarInitials()"
                                :avatar-color="$topbarAvatarUser?->presenceAvatarColor()"
                                :show-workstation-switch="$workstationPresence->workstation !== null"
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
                                Training snoozed for {{ session('learn_snoozed.hours') }} hours — back at <strong>{{ session('learn_snoozed.until_label') }}</strong>.
                            </span>
                            <a href="{{ \App\Ark\Operations\Learn\ArkademyUrls::staffNavUrl() }}">Resume guides</a>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>

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
    </body>
</html>
