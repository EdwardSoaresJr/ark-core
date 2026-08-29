<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\Recognition\CustomerRecognitionProjection;
use App\Ark\Operations\EmailVerification\EmailVerification;
use App\Ark\Operations\EmailVerification\EmailVerificationAuthority;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\Public\LeadPhoneVerification;
use App\Ark\Operations\PhoneVerification\PhoneVerification;
use App\Ark\Operations\PhoneVerification\PhoneVerificationAuthority;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Mail\BookIdentityCodeMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('public_lead.phone_verification_required', false);

    ShopSettings::current()->update([
        'shop_name' => 'Demo Auto Repair',
        'shop_timezone' => 'America/Denver',
        'twilio_account_sid' => 'ACtestverify',
        'twilio_auth_token' => 'test-token',
        'telephony_inbound_number' => '7195559999',
        'learn_training_gate_enabled' => false,
        'appointment_request_availability' => [
            'weekly' => [
                'monday' => ['enabled' => true],
                'tuesday' => ['enabled' => true],
                'wednesday' => ['enabled' => true],
                'thursday' => ['enabled' => true],
                'friday' => ['enabled' => true],
                'saturday' => ['enabled' => false],
                'sunday' => ['enabled' => false],
            ],
            'horizon_days' => 14,
            'minimum_notice_days' => 0,
        ],
    ]);
    ShopSettings::forgetCurrent();

    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'SMtest', 'status' => 'queued'], 201),
    ]);
});

function bookPlantVerificationCode(string $code = '123456'): void
{
    PhoneVerification::query()->latest('id')->first()?->forceFill([
        'code_hash' => PhoneVerification::hashCode($code),
    ])->save();
}

test('unknown phone after SMS verify enters guest wizard not recognition', function (): void {
    $this->postJson(route('public.leads.verify.send'), [
        'phone' => '719-555-0142',
        'book_identity' => true,
    ])->assertOk();

    bookPlantVerificationCode();

    $this->postJson(route('public.leads.verify.check'), [
        'phone' => '719-555-0142',
        'code' => '123456',
        'book_identity' => true,
    ])->assertOk()->assertJson(['verified' => true]);

    $this->post(route('public.book.identity'), [
        'phone' => '719-555-0142',
    ])->assertRedirect(route('public.book'));

    expect(session(CustomerRecognitionProjection::SESSION_GUEST_KEY))->toBeTrue()
        ->and(auth('portal')->check())->toBeFalse();

    $this->get(route('public.book'))
        ->assertOk()
        ->assertSee('What can we help you with?', false)
        ->assertDontSee('Welcome back', false);
});

test('known phone after SMS verify logs into portal recognition', function (): void {
    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Customer',
        'phone' => '7195551212',
        'email' => 'molly.book@example.test',
        'customer_type' => 'Retail',
    ]);

    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Jeep',
        'model' => 'Wrangler',
    ]);

    $this->postJson(route('public.leads.verify.send'), [
        'phone' => '719-555-1212',
        'book_identity' => true,
    ])->assertOk();

    bookPlantVerificationCode();

    $this->postJson(route('public.leads.verify.check'), [
        'phone' => '719-555-1212',
        'code' => '123456',
        'book_identity' => true,
    ])->assertOk();

    $this->post(route('public.book.identity'), [
        'phone' => '719-555-1212',
    ])->assertRedirect(route('public.book'));

    expect(auth('portal')->id())->toBe($customer->id)
        ->and(session(CustomerRecognitionProjection::SESSION_GUEST_KEY))->toBeNull();

    $this->get(route('public.book'))
        ->assertOk()
        ->assertSee('Welcome back, Molly.', false)
        ->assertSee('2014 Jeep Wrangler', false);
});

function bookPlantEmailVerificationCode(string $code = '123456'): void
{
    EmailVerification::query()->latest('id')->first()?->forceFill([
        'code_hash' => EmailVerification::hashCode($code),
    ])->save();
}

test('book email identity stays on book routes and recognizes known customer', function (): void {
    Mail::fake();

    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Customer',
        'phone' => '7195551212',
        'email' => 'molly.book@example.test',
        'customer_type' => 'Retail',
    ]);

    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Jeep',
        'model' => 'Wrangler',
    ]);

    $this->postJson(route('public.book.identity.email.send'), [
        'email' => 'molly.book@example.test',
    ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(EmailVerification::query()->count())->toBe(1);
    Mail::assertSent(BookIdentityCodeMail::class);

    bookPlantEmailVerificationCode();

    $this->postJson(route('public.book.identity.email.check'), [
        'email' => 'molly.book@example.test',
        'code' => '123456',
    ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('redirect', route('public.book'));

    expect(auth('portal')->id())->toBe($customer->id);

    $this->get(route('public.book'))
        ->assertOk()
        ->assertSee('Welcome back, Molly.', false);
});

test('book email identity does not leave modal for portal access url', function (): void {
    $this->get(route('public.book'))
        ->assertOk()
        ->assertSee('Use email instead', false)
        ->assertSee('Enter your email', false)
        ->assertDontSee('has for you', false)
        ->assertDontSee(route('portal.access', ['return' => '/book']), false);
});

test('unknown book email receives code and continues as guest', function (): void {
    Mail::fake();

    $this->postJson(route('public.book.identity.email.send'), [
        'email' => 'unknown@example.test',
    ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(EmailVerification::query()->count())->toBe(1);
    Mail::assertSent(BookIdentityCodeMail::class);

    bookPlantEmailVerificationCode();

    $this->postJson(route('public.book.identity.email.check'), [
        'email' => 'unknown@example.test',
        'code' => '123456',
    ])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('redirect', route('public.book'));

    expect(auth('portal')->check())->toBeFalse()
        ->and(session(CustomerRecognitionProjection::SESSION_GUEST_KEY))->toBeTrue()
        ->and(session(EmailVerificationAuthority::SESSION_KEY)['email'] ?? null)->toBe('unknown@example.test');

    $this->get(route('public.book'))
        ->assertOk()
        ->assertSee('What can we help you with?', false)
        ->assertDontSee('Welcome back', false);
});

test('appointment request without book SMS proof is rejected even when global verify flag is off', function (): void {
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-07-24 10:00:00', 'America/Denver'));

    $this->post(route('public.leads.store'), [
        'concern' => 'Brakes',
        'preferred_date' => '2026-07-27',
        'preferred_period' => 'morning',
        'phone' => '719-555-0142',
        'first_name' => 'Alex',
        'last_name' => 'Morgan',
        'source' => LeadSource::Website->value,
        'form_rendered_at' => now()->subSeconds(5)->timestamp,
        'public_surface_page' => 'book',
        'public_surface_variant' => 'appointment_request',
        'public_surface_placement' => 'embedded_form',
    ])
        ->assertSessionHasErrors('phone');

    expect(Lead::query()->count())->toBe(0);

    \Illuminate\Support\Carbon::setTestNow();
});

test('appointment request accepts consumeBookProof after identity gate', function (): void {
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-07-24 10:00:00', 'America/Denver'));

    session([
        PhoneVerificationAuthority::SESSION_KEY => [
            'phone' => '7195550142',
            'verified_at' => now()->timestamp,
        ],
        CustomerRecognitionProjection::SESSION_GUEST_KEY => true,
    ]);

    $this->post(route('public.leads.store'), [
        'concern' => 'Brakes',
        'preferred_date' => '2026-07-27',
        'preferred_period' => 'morning',
        'phone' => '719-555-0142',
        'first_name' => 'Alex',
        'last_name' => 'Morgan',
        'source' => LeadSource::Website->value,
        'form_rendered_at' => now()->subSeconds(5)->timestamp,
        'public_surface_page' => 'book',
        'public_surface_variant' => 'appointment_request',
        'public_surface_placement' => 'embedded_form',
    ])->assertRedirect(route('public.leads.thanks'));

    expect(Lead::query()->sole()->contact_phone)->toBe('7195550142')
        ->and(session(LeadPhoneVerification::SESSION_KEY))->toBeNull();

    \Illuminate\Support\Carbon::setTestNow();
});

test('appointment request accepts verified email proof without phone SMS session', function (): void {
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-07-24 10:00:00', 'America/Denver'));

    session([
        EmailVerificationAuthority::SESSION_KEY => [
            'email' => 'newlead@example.test',
            'verified_at' => now()->timestamp,
        ],
        CustomerRecognitionProjection::SESSION_GUEST_KEY => true,
    ]);

    $this->post(route('public.leads.store'), [
        'concern' => 'Brakes',
        'preferred_date' => '2026-07-27',
        'preferred_period' => 'morning',
        'phone' => '719-555-0142',
        'email' => 'newlead@example.test',
        'first_name' => 'Alex',
        'last_name' => 'Morgan',
        'source' => LeadSource::Website->value,
        'form_rendered_at' => now()->subSeconds(5)->timestamp,
        'public_surface_page' => 'book',
        'public_surface_variant' => 'appointment_request',
        'public_surface_placement' => 'embedded_form',
    ])->assertRedirect(route('public.leads.thanks'));

    expect(Lead::query()->sole()->contact_email)->toBe('newlead@example.test')
        ->and(session(EmailVerificationAuthority::SESSION_KEY))->toBeNull();

    \Illuminate\Support\Carbon::setTestNow();
});
