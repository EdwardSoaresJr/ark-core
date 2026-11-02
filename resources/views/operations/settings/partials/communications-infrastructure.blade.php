@php
    /** @var \App\Ark\Operations\Telephony\TelephonyHealth $telephonyHealth */

    $toneAlerts = [
        'success' => 'alert-success',
        'warning' => 'alert-warning',
        'danger' => 'alert-danger',
        'muted' => 'alert-secondary',
    ];
    $platformVoiceManaged = (bool) ($platformVoiceManaged ?? \App\Ark\Platform\Voice\ManagedVoiceGate::platformVoiceReady());
@endphp

@if (auth()->user()?->isMasterAdmin())
    <div class="card-body" data-runtime-communications-infrastructure>
        <div style="max-width: 48rem">
            <div class="subheader">Communications infrastructure</div>
            @if ($platformVoiceManaged)
                <h3 class="mb-1">Hosted Voice (ARK Cloud)</h3>
                <p class="small text-secondary">
                    Inbound Voice for this installation is executed on ARK Platform. Core receives CallSession and media metadata over Connect/Fabric -
                    Core Twilio Voice webhook URLs below are legacy and must not be the Twilio Voice URL for the Hosted shop number.
                </p>
                <div class="small">
                    <div class="mb-2">
                        <div class="fw-medium">Fabric ingress</div>
                        <div class="font-monospace text-secondary text-break">{{ url('/webhooks/cloud/fabric/events') }}</div>
                    </div>
                    <div class="mb-2">
                        <div class="fw-medium">Phone settings</div>
                        <a href="https://cloud.arksms.com" target="_blank" rel="noopener">cloud.arksms.com</a>
                    </div>
                    <details class="mt-2">
                        <summary class="fw-medium" style="cursor: pointer">Legacy Core Voice URLs (do not point Twilio here for Hosted)</summary>
                        <div class="font-monospace text-secondary text-break mt-2">{{ $telephonyHealth->webhookUrl() }}</div>
                        <div class="font-monospace text-secondary text-break mt-1">{{ $telephonyHealth->sipOutboundWebhookUrl() }}</div>
                    </details>
                </div>
            @else
                <h3 class="mb-1">Twilio voice transport</h3>
                <p class="small text-secondary">
                    Voice runs through Twilio webhooks and SIP endpoints. Shop devices, coverage, and operators live under Shop → Communications.
                </p>

                <div class="row g-2">
                    <div class="col-12 col-sm-6">
                        <div class="alert {{ $toneAlerts[$telephonyHealth->connectionTone()] ?? $toneAlerts['muted'] }} mb-0" role="status">
                            <div class="subheader">Connection</div>
                            <div class="fw-bold">{{ $telephonyHealth->connectionLabel() }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="alert {{ $toneAlerts[$telephonyHealth->webhookTone()] ?? $toneAlerts['muted'] }} mb-0" role="status">
                            <div class="subheader">Voice webhook</div>
                            <div class="fw-bold">{{ $telephonyHealth->webhookLabel() }}</div>
                        </div>
                    </div>
                </div>

                <div class="small mt-3">
                    <div class="mb-2">
                        <div class="fw-medium">Voice inbound</div>
                        <div class="font-monospace text-secondary text-break">{{ $telephonyHealth->webhookUrl() }}</div>
                    </div>
                    <div class="mb-2">
                        <div class="fw-medium">SIP outbound</div>
                        <div class="font-monospace text-secondary text-break">{{ $telephonyHealth->sipOutboundWebhookUrl() }}</div>
                    </div>
                    <div class="text-secondary">{{ $telephonyHealth->credentialSourceLabel() }}</div>
                </div>
            @endif
        </div>
    </div>
@endif
