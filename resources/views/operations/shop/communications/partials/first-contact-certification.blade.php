@php
    /** @var array<string, mixed> $workspace */
    $unknownMacProbeUrl = url('/provision/AABBCCDDEEFF.cfg');
@endphp

<section id="certification" class="card" aria-labelledby="certification-title">
    <div class="card-status-top bg-green"></div>
    <div class="card-header">
        <div>
            <h2 class="card-title" id="certification-title">Certification · G1–G7</h2>
            <div class="card-subtitle">
                Factory-reset this phone, follow each gate in order, and record timestamps. Name failed gates (e.g. Gate G5), not subsystems.
            </div>
        </div>
    </div>

    <div class="card-body p-2 p-md-3">
        <div class="datagrid">
            <div class="datagrid-item">
                <div class="datagrid-title">Device</div>
                <div class="datagrid-content fw-bold">{{ $workspace['device']->name }}</div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">Station</div>
                <div class="datagrid-content">{{ $workspace['workstation_name'] ?? '-' }}</div>
            </div>
            <div class="datagrid-item">
                <div class="datagrid-title">MAC</div>
                <div class="datagrid-content font-monospace">{{ $workspace['mac_address_display'] ?? '-' }}</div>
            </div>
        </div>
        @if ($workspace['provision_url'])
            <div class="mt-3">
                <div class="datagrid-title">Bench test URL (G4)</div>
                <code class="d-block text-break mt-1">{{ $workspace['provision_url'] }}</code>
            </div>
        @endif
        @if ($workspace['provisioning_host'])
            <div class="mt-3">
                <div class="datagrid-title">Voice host</div>
                <div class="font-monospace small">{{ $workspace['provisioning_host'] }}</div>
            </div>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table card-table table-vcenter">
            <thead>
                <tr>
                    <th>Gate</th>
                    <th>Question</th>
                    <th>Expected</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="fw-bold">G1</td>
                    <td>Is production healthy?</td>
                    <td>
                        <a href="{{ url('/up') }}" target="_blank" rel="noopener">/up</a> = 200
                    </td>
                </tr>
                <tr>
                    <td class="fw-bold">G2</td>
                    <td>Is the schema current?</td>
                    <td>Migrations applied; device models seeded</td>
                </tr>
                <tr>
                    <td class="fw-bold">G3</td>
                    <td>Is provisioning alive?</td>
                    <td>
                        Unknown MAC → <strong>404</strong> (not 500)
                        <code class="d-block text-break mt-1">{{ $unknownMacProbeUrl }}</code>
                    </td>
                </tr>
                <tr>
                    <td class="fw-bold">G4</td>
                    <td>Do provisioning gates work?</td>
                    <td>
                        This device configured → GET provision URL → <strong>200</strong> + Poly body
                    </td>
                </tr>
                <tr>
                    <td class="fw-bold">G5</td>
                    <td>Does the phone consume configuration?</td>
                    <td>
                        Factory reset · disable Poly ZTP · custom server URL above
                        (<code class="text-break">{{ $workspace['provisioning_server_url'] }}</code>,
                        {{ strtolower($workspace['provisioning_server_scheme']) }}, no server user/pass)
                    </td>
                </tr>
                <tr>
                    <td class="fw-bold">G6</td>
                    <td>Does the device register?</td>
                    <td>Voice host shows endpoint registered</td>
                </tr>
                <tr>
                    <td class="fw-bold">G7</td>
                    <td>Does ARK observe reality?</td>
                    <td class="fw-medium text-success">Device status → Connected</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card-footer small text-secondary">
        Record gate timestamps on the floor. Full checklist:
        <span class="fw-medium text-body">docs/communications/first-contact-floor-checklist.md</span>
    </div>
</section>
