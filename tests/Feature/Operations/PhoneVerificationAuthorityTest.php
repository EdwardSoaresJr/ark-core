<?php

use App\Ark\Operations\PhoneVerification\PhoneVerification;
use App\Ark\Operations\PhoneVerification\PhoneVerificationAuthority;
use App\Ark\Operations\PhoneVerification\PhoneVerificationException;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;


beforeEach(function (): void {
    ShopSettings::current()->update([
        'shop_name' => 'Demo Auto Repair',
        'telephony_inbound_number' => '7195559999',
    ]);
    ShopSettings::forgetCurrent();
    bindFakeOutboundSms();
});

test('issue sends OTP via programmable messaging not Twilio Verify', function (): void {
    $authority = app(PhoneVerificationAuthority::class);
    $authority->issue('719-555-0142', '127.0.0.1', 'Pest');

    expect(PhoneVerification::query()->count())->toBe(1);
});

test('verify establishes session without logging into portal', function (): void {
    $authority = app(PhoneVerificationAuthority::class);
    $session = session()->driver();
    $authority->issue('719-555-0142', '127.0.0.1');

    PhoneVerification::query()->latest('id')->first()->forceFill([
        'code_hash' => PhoneVerification::hashCode('483291'),
    ])->save();

    expect($authority->verify($session, '719-555-0142', '483291'))->toBeTrue()
        ->and($authority->verifiedPhone($session))->toBe('7195550142')
        ->and(auth('portal')->check())->toBeFalse();
});

test('wrong codes burn attempts then require a new issue', function (): void {
    $authority = app(PhoneVerificationAuthority::class);
    $session = session()->driver();
    $authority->issue('719-555-0142', '127.0.0.1');

    PhoneVerification::query()->latest('id')->first()->forceFill([
        'code_hash' => PhoneVerification::hashCode('483291'),
        'attempts_remaining' => 2,
    ])->save();

    expect(fn () => $authority->verify($session, '719-555-0142', '000000'))
        ->toThrow(PhoneVerificationException::class);

    expect(fn () => $authority->verify($session, '719-555-0142', '000000'))
        ->toThrow(PhoneVerificationException::class);

    expect(fn () => $authority->verify($session, '719-555-0142', '483291'))
        ->toThrow(PhoneVerificationException::class);
});

test('consumeVerifiedSession is one-time', function (): void {
    $authority = app(PhoneVerificationAuthority::class);
    $session = session()->driver();
    $authority->issue('719-555-0142', '127.0.0.1');
    PhoneVerification::query()->latest('id')->first()->forceFill([
        'code_hash' => PhoneVerification::hashCode('111111'),
    ])->save();
    $authority->verify($session, '719-555-0142', '111111');

    expect($authority->consumeVerifiedSession($session, '719-555-0142'))->toBeTrue()
        ->and($authority->verifiedPhone($session))->toBeNull()
        ->and($authority->consumeVerifiedSession($session, '719-555-0142'))->toBeFalse()
        ->and(PhoneVerification::query()->sole()->consumed_at)->not->toBeNull();
});

test('send cooldown blocks rapid resend', function (): void {
    $authority = app(PhoneVerificationAuthority::class);
    $authority->issue('719-555-0142', '127.0.0.1');

    expect(fn () => $authority->issue('719-555-0142', '127.0.0.1'))
        ->toThrow(PhoneVerificationException::class, 'Wait a moment');
});
