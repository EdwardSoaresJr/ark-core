@php
    $user = auth()->user();
    $commsBadgeClass = match (true) {
        (bool) ($commsPressure['has_live_calls'] ?? false) => 'bg-red text-red-fg',
        ($commsNavSummary['since_last_shift_count'] ?? 0) > 0 => 'bg-orange text-orange-fg',
        default => 'bg-red-lt',
    };
    $topbarAvatarUser = $workstationPresence->currentOperator ?? $user;
    $channelTotal = (int) (collect($commsChannelTabs)->firstWhere('slug', 'all')['count'] ?? 0);
@endphp

<div class="page">
    <aside class="navbar navbar-vertical navbar-expand-lg d-print-none" data-bs-theme="dark" aria-label="Primary operations">
        <div class="container-fluid">
            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#ark-sidebar-menu"
                aria-controls="ark-sidebar-menu"
                aria-expanded="false"
                aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="navbar-brand">
                <a href="{{ $staffFrontDoorUrl }}">
                    <img
                        src="{{ \App\Support\Branding\Branding::logo('full_white') }}"
                        alt="{{ \App\Support\Branding\Branding::tabTitle() }}"
                        class="navbar-brand-image"
                        width="1432"
                        height="434"
                    >
                </a>
            </div>
            <div class="collapse navbar-collapse" id="ark-sidebar-menu">
                <ul class="navbar-nav pt-lg-3">
                    @foreach ($navSections as $section)
                        @if ($section['label'] !== null)
                            <li class="nav-item mt-3">
                                <div class="subheader px-3 pb-1">{{ $section['label'] }}</div>
                            </li>
                        @endif
                        @foreach ($section['items'] as $item)
                            <li @class(['nav-item', 'active' => $item['active']])>
                                <a
                                    href="{{ $item['url'] }}"
                                    class="nav-link"
                                    @if ($item['active']) aria-current="page" @endif
                                    @if ($item['live_count'])
                                        data-ops-comms-nav-link
                                        data-ops-comms-nav-style="tabler"
                                        data-initial-count="{{ $commsNavCount }}"
                                    @endif
                                >
                                    <span class="nav-link-icon">
                                        <x-tabler.icon :name="$item['icon']" />
                                    </span>
                                    <span class="nav-link-title">{{ $item['label'] }}</span>
                                    @if ($item['live_count'] && $commsNavCount > 0)
                                        <span class="badge ms-auto {{ $commsBadgeClass }}" data-ops-comms-nav-count aria-label="{{ $commsNavCount }} need attention">{{ $commsNavCount }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        </div>
    </aside>

    <div class="page-wrapper">
        <header class="navbar navbar-expand d-print-none">
            <div class="container-fluid gap-2">
                @if ($showTopbarCommsStrip)
                    <div class="navbar-nav d-lg-none">
                        <div class="nav-item dropdown">
                            <a
                                href="#"
                                class="nav-link px-2"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                                aria-label="Communications, {{ $channelTotal }} need attention"
                            >
                                <x-tabler.icon name="message" />
                                @if ($channelTotal > 0)
                                    <span class="badge bg-red text-red-fg badge-notification badge-pill">{{ $channelTotal }}</span>
                                @endif
                            </a>
                            <div class="dropdown-menu dropdown-menu-arrow">
                                <span class="dropdown-header">Communications</span>
                                @foreach ($commsChannelTabs as $tab)
                                    <a href="{{ $tab['url'] }}" class="dropdown-item">
                                        {{ $tab['label'] }}
                                        <span @class([
                                            'badge ms-auto',
                                            'bg-red text-red-fg' => $tab['count'] > 0,
                                            'bg-secondary-lt' => $tab['count'] === 0,
                                        ])>{{ $tab['count'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="flex-fill overflow-x-auto d-none d-lg-block">
                        @include('operations.communications.partials.channel-strip', [
                            'comms_channel_tabs' => $commsChannelTabs,
                            'variant' => 'tabler',
                        ])
                    </div>
                @endif
                <div class="navbar-nav flex-row align-items-center gap-2 ms-auto">
                    <x-operations.workstation-presence :presence="$workstationPresence" ui="tabler" />
                    @include('components.operations.shell.tabler-user-menu', [
                        'menuUser' => $user,
                        'avatarUser' => $topbarAvatarUser,
                    ])
                </div>
            </div>
        </header>

        {{-- ark-workspace-tabs.js builds this strip's tabs, overflow menu, and drag states with ops-workspace-tab* classes styled by app.css. --}}
        <div class="ark-legacy">
            @include('components.operations.workspace-tabs')
        </div>

        @can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
            @if ($commsAccountabilityEnabled && $commsNavCount > 0 && ! request()->routeIs('operations.index', 'operations.communications.attention', 'operations.communications.inbox', 'operations.communications.history', 'operations.communications.calls', 'operations.communications.internal', 'operations.communications.internal.channel'))
                <x-operations.comms-pressure-bar ui="tabler" :pressure="[
                    'count' => $commsNavCount,
                    'summary' => $commsNavSummary,
                    'attention_url' => $commsPressure['attention_url'],
                    'has_live_calls' => (bool) ($commsNavSummary['has_live_calls'] ?? false),
                ]" />
            @endif
        @endcan

        @if ($learnTrainingSnooze || session('learn_snoozed'))
            <div class="container-fluid pt-3">
                <div class="alert alert-info mb-0" role="status">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <div class="flex-fill">
                            @if ($learnTrainingSnooze)
                                Required training snoozed until <strong>{{ $learnTrainingSnooze['snoozed_until_label'] }}</strong>.
                            @else
                                Training snoozed for {{ session('learn_snoozed.hours') }} hours - back at <strong>{{ session('learn_snoozed.until_label') }}</strong>.
                            @endif
                        </div>
                        <a href="{{ \App\Ark\Operations\Learn\ArkademyUrls::staffNavUrl() }}" class="alert-link">Resume guides</a>
                        @if ($learnTrainingSnooze && ($canSnoozeTraining ?? false))
                            <form method="POST" action="{{ route('operations.learn.progress.snooze') }}">
                                @csrf
                                <button type="submit" class="btn btn-sm">
                                    Snooze {{ \App\Ark\Operations\Learn\LearnArkCurriculum::SNOOZE_HOURS }}h more
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <main>
            {{ $slot }}
        </main>
    </div>
</div>

{{--
    Each overlay is one .modal (z-index 1055, above the sidebar) with its backdrop inside, so later overlays cover earlier
    ones: this order keeps the legacy stacking.
--}}
@can(App\Ark\Runtime\Authorization\ArkCapability::OperationsAccess->value)
    <x-operations.global-search ui="tabler" />
    <x-operations.learn.guide-modal ui="tabler" />
    <x-operations.comms-interrupt-panel ui="tabler" />
    <x-operations.call-queue :poller-only="true" ui="tabler" />
@endcan
<x-operations.image-lightbox ui="tabler" />
