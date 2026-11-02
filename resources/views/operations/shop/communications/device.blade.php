@php
    /** @var array<string, mixed> $workspace */
    $device = $workspace['device'];
    $stationName = $workspace['workstation_name'] ?? $device->name;
    $toneText = static fn (string $tone): string => match ($tone) {
        'success' => 'text-success',
        'warning' => 'text-warning',
        'danger' => 'text-danger',
        default => 'text-secondary',
    };
    $showLocation = $workspace['workstation_location'] && $workspace['workstation_location'] !== $stationName;
@endphp

<x-operations.app :title="$stationName.' · Communications'" ui="tabler">
    <x-tabler.page
        layout="focused"
        :title="$stationName"
        pretitle="Stations & Phones"
        :subtitle="$showLocation ? $workspace['workstation_location'] : null"
        actions-label="Station commands"
        stack-actions
    >
        <x-slot:actions>
            <x-tabler.page-action :href="route('operations.shop.communications')" icon="arrow-left" label="Stations & Phones" />
            @if ($workspace['first_contact']['ready'] && auth()->user()?->isMasterAdmin())
                <x-tabler.page-action href="#certification" icon="list-check" label="Bench certification" primary />
            @endif
        </x-slot:actions>

        @if ($showLocation)
            <p class="text-secondary d-md-none">{{ $workspace['workstation_location'] }}</p>
        @endif

        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif

        <div class="d-flex flex-column gap-3">
            @include('operations.shop.communications.partials.first-contact-readiness', [
                'firstContact' => $workspace['first_contact'],
            ])

            <section class="card" aria-label="Station status" data-shop-device-status>
                <div class="card-body p-2 p-md-3">
                    <div class="datagrid">
                        <div class="datagrid-item">
                            <div class="datagrid-title">Status</div>
                            <div @class(['datagrid-content fw-bold', $toneText($workspace['status_tone'])])>
                                {{ $workspace['status_label'] === 'Connected' ? 'Ready' : $workspace['status_label'] }}
                            </div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Current operator</div>
                            <div class="datagrid-content fw-bold">
                                {{ $workspace['current_operator_label'] ?? 'Not signed in' }}
                            </div>
                        </div>
                        @if ($workspace['current_activity'] !== 'Idle')
                            <div class="datagrid-item">
                                <div class="datagrid-title">Right now</div>
                                <div @class(['datagrid-content fw-bold', $toneText($workspace['current_activity_tone'])])>{{ $workspace['current_activity'] }}</div>
                            </div>
                        @endif
                        @if ($workspace['last_session_headline'])
                            <div class="datagrid-item">
                                <div class="datagrid-title">Last call</div>
                                <div class="datagrid-content fw-bold">{{ $workspace['last_session_headline'] }}</div>
                                @if ($workspace['last_session_when'])
                                    <div class="small text-secondary">{{ $workspace['last_session_when'] }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
                <div class="card-body p-2 p-md-3 border-top">
                    <div class="datagrid-title">Attached devices</div>
                    <div class="d-flex flex-wrap justify-content-between column-gap-3 mt-1">
                        <span class="fw-medium">{{ $device->name }}</span>
                        <span @class(['small fw-bold text-uppercase', $toneText($workspace['status_tone'])])>
                            {{ $workspace['status_label'] }}
                        </span>
                    </div>
                    @if ($workspace['device_model_label'])
                        <div class="small text-secondary">{{ $workspace['device_model_label'] }}</div>
                    @endif
                </div>
            </section>

            @if ($workspace['first_contact']['ready'])
                @include('operations.shop.communications.partials.poly-provisioning-instructions', [
                    'workspace' => $workspace,
                ])

                @if (auth()->user()?->isMasterAdmin())
                    @include('operations.shop.communications.partials.first-contact-certification', [
                        'workspace' => $workspace,
                    ])
                @endif
            @endif

            @if ($errors->has('device'))
                <div class="alert alert-danger mb-0" role="alert">{{ $errors->first('device') }}</div>
            @endif

            @if (auth()->user()?->isMasterAdmin())
                <section class="card" aria-labelledby="device-infrastructure-title">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title" id="device-infrastructure-title">Infrastructure</h2>
                            <div class="card-subtitle">Bench tools - not part of daily operations.</div>
                        </div>
                    </div>
                    <div class="card-body p-2 p-md-3">
                        <div class="datagrid">
                            <div class="datagrid-item">
                                <div class="datagrid-title">MAC</div>
                                <div class="datagrid-content font-monospace fw-medium">{{ $workspace['mac_address_display'] ?? 'Not set' }}</div>
                            </div>
                            <div class="datagrid-item">
                                <div class="datagrid-title">Projection</div>
                                <div class="datagrid-content fw-medium">{{ $workspace['projection_status'] }}</div>
                            </div>
                        </div>

                        @if ($workspace['projection_serialized_config'])
                            <pre class="mt-3 mb-0 overflow-auto" style="max-height: 12rem">{{ $workspace['projection_serialized_config'] }}</pre>
                        @endif
                    </div>
                    @if ($workspace['can_generate_config'] || $workspace['can_download_config'])
                        <div class="card-footer">
                            <div class="btn-list">
                                @if ($workspace['can_generate_config'])
                                    <form method="POST" action="{{ route('operations.shop.devices.provision.generate', $device) }}" class="d-inline-flex">
                                        @csrf
                                        <button type="submit" class="btn btn-sm">
                                            Regenerate config
                                        </button>
                                    </form>
                                @endif
                                @if ($workspace['can_download_config'])
                                    <a href="{{ route('operations.shop.devices.provision.download', $device) }}" class="btn btn-sm">
                                        Download config
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
                </section>
            @endif

            <section class="card">
                <div class="card-body p-2 p-md-3">
                    <p class="text-secondary">Remove this device to re-add after factory reset or bench testing. The station keeps its place in the shop.</p>
                    <form
                        method="POST"
                        action="{{ route('operations.shop.devices.destroy', $device) }}"
                        onsubmit="return confirm('Remove {{ $device->name }} from {{ $stationName }}?')"
                    >
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <x-tabler.icon name="trash" />
                            Remove device
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </x-tabler.page>
</x-operations.app>
