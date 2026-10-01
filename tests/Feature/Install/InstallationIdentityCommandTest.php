<?php

use App\Ark\Install\InstallationIdentity;
use Illuminate\Support\Str;

test('installation identity show does not mint a uuid', function (): void {
    @unlink(InstallationIdentity::path());

    $this->artisan('ark:installation-identity', ['action' => 'show'])
        ->expectsOutput('missing')
        ->assertSuccessful();

    expect(InstallationIdentity::read())->toBeNull();
});

test('installation identity write stores the given uuid once', function (): void {
    @unlink(InstallationIdentity::path());
    $uuid = (string) Str::uuid();

    $this->artisan('ark:installation-identity', [
        'action' => 'write',
        '--uuid' => $uuid,
    ])->expectsOutput($uuid)->assertSuccessful();

    expect(InstallationIdentity::read())->toBe($uuid);

    $this->artisan('ark:installation-identity', [
        'action' => 'write',
        '--uuid' => (string) Str::uuid(),
    ])->assertFailed();

    expect(InstallationIdentity::read())->toBe($uuid);
});
