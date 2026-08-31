<?php

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Leads\Public\LeadPhoneVerification;
use App\Ark\Operations\PhoneVerification\PhoneVerification;
use App\Ark\Operations\PhoneVerification\PhoneVerificationAuthority;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('public_lead.phone_verification_required', true);

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);
    ShopSettings::forgetCurrent();

    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'SMtest', 'status' => 'queued'], 201),
    ]);
    bindFakeOutboundSms();
});

test('website lead requires verified phone when verify is enabled', function (): void {
    $this->post(route('public.leads.store'), [
        'concern' => 'Brakes squeal when stopping.',
        'phone' => '719-555-0142',
        'first_name' => 'Alex',
        'last_name' => 'Morgan',
        'form_rendered_at' => now()->subSeconds(10)->timestamp,
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('phone');

    expect(Lead::query()->count())->toBe(0);
});

test('website lead submits after phone verification session', function (): void {
    $this->postJson(route('public.leads.verify.send'), ['phone' => '719-555-0142'])
        ->assertOk();

    PhoneVerification::query()->latest('id')->first()->forceFill([
        'code_hash' => PhoneVerification::hashCode('123456'),
    ])->save();

    $this->postJson(route('public.leads.verify.check'), [
        'phone' => '719-555-0142',
        'code' => '123456',
    ])->assertOk()->assertJson(['verified' => true]);

    $this->post(route('public.leads.store'), [
        'concern' => 'Brakes squeal when stopping.',
        'phone' => '719-555-0142',
        'first_name' => 'Alex',
        'last_name' => 'Morgan',
        'form_rendered_at' => now()->subSeconds(10)->timestamp,
    ])->assertRedirect(route('public.leads.thanks'));

    $lead = Lead::query()->sole();

    expect($lead->state)->toBe(LeadState::Received)
        ->and($lead->contact_phone)->toBe('7195550142');
});

test('verification session must match submitted phone', function (): void {
    session([
        PhoneVerificationAuthority::SESSION_KEY => [
            'phone' => '7195550142',
            'verified_at' => now()->timestamp,
        ],
    ]);

    $this->post(route('public.leads.store'), [
        'concern' => 'AC not cold',
        'phone' => '719-555-9999',
        'first_name' => 'Alex',
        'last_name' => 'Morgan',
        'form_rendered_at' => now()->subSeconds(10)->timestamp,
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('phone');
});

test('book identity gate is ready when shop Programmable Messaging is configured', function (): void {
    expect(app(LeadPhoneVerification::class)->bookIdentityGateReady())->toBeTrue();
});
