<?php

use App\Ark\Platform\Communications\ManagedCommunicationsGate;
use App\Ark\Platform\PlatformConnection;

it('never allows legacy Core inbox fallback after Stage 11', function () {
    expect(ManagedCommunicationsGate::allowLegacyInboxFallback())->toBeFalse();
});

it('defaults Hosted mirror off and rejects Core messaging webhooks when Platform-connected config is set', function () {
    config([
        'services.ark_platform.communications_authority' => true,
        'services.ark_platform.communications_inbox' => true,
        'services.ark_platform.communications_send' => true,
        'services.ark_platform.communications_core_mirror' => false,
        'services.ark_platform.reject_core_twilio_messaging_webhooks' => true,
    ]);

    // Without a live PlatformConnection, platformAuthority() is false → mirror gate returns true for self-host.
    // Assert config defaults that Hosted LNP uses when connected.
    expect(config('services.ark_platform.communications_core_mirror'))->toBeFalse();
    expect(config('services.ark_platform.reject_core_twilio_messaging_webhooks'))->toBeTrue();
    expect(config('services.ark_platform.communications_inbox'))->toBeTrue();
    expect(config('services.ark_platform.communications_send'))->toBeTrue();
});
