@php
    /** @var array<string, mixed> $workspace */
@endphp

<section class="card" aria-labelledby="connect-this-device-title">
    <div class="card-status-top bg-azure"></div>
    <div class="card-header">
        <div>
            <h2 class="card-title" id="connect-this-device-title">Connect this device</h2>
            <div class="card-subtitle">
                After factory reset, enter this provisioning server on the phone. Credentials download automatically - nothing else to type on the device.
            </div>
        </div>
    </div>

    <div class="card-body p-2 p-md-3">
        <div class="datagrid-title">Provisioning server</div>
        <code class="d-block text-break mt-1 mb-3">{{ $workspace['provisioning_server_url'] }}</code>

        <div class="datagrid">
            <div class="datagrid-item">
                <div class="datagrid-title">Protocol</div>
                <div class="datagrid-content fw-medium">{{ $workspace['provisioning_server_scheme'] }}</div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">Server user</div>
                <div class="datagrid-content text-secondary">Leave blank</div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">Server password</div>
                <div class="datagrid-content text-secondary">Leave blank</div>
            </div>
        </div>

        @if ($workspace['provision_url'] && auth()->user()?->isMasterAdmin())
            <div class="mt-3 pt-3 border-top">
                <div class="datagrid-title">Bench verification URL</div>
                <code class="d-block text-break mt-1">{{ $workspace['provision_url'] }}</code>
            </div>
        @endif
    </div>
</section>
