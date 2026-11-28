<?php

use App\Ark\Runtime\InfrastructureProfile;

it('reports an infrastructure profile without treating it as a commercial entitlement', function (): void {
    $profile = InfrastructureProfile::inspect();

    expect($profile['profile'])->toBeIn([
        InfrastructureProfile::FULL,
        InfrastructureProfile::STANDARD,
        InfrastructureProfile::CONSTRAINED,
    ])
        ->and($profile)->toHaveKeys([
            'redis_extension',
            'redis_reachable',
            'redis_configured',
            'chromium_pdf',
            'native_pdf',
            'detached_finalize',
            'long_running_workers_configured',
            'websockets_configured',
            'websockets_usable',
            'notes',
        ])
        ->and($profile['notes'])->toBeArray();
});

it('detects when detached finalize is blocked by disable_functions semantics', function (): void {
    expect(InfrastructureProfile::detachedFinalizeAvailable())->toBeBool();
});
