<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Portal\PortalAccessChallenge;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Mail\PortalAccessCodeMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
        
    ShopSettings::current()->update([
        'shop_name' => 'Demo Auto Repair',
        'telephony_inbound_number' => '7195559999',
    ]);
});

test('customer can access portal with sms code', function () {
    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMportal001',
            'status' => 'queued',
        ], 201),
    ]);
    bindFakeOutboundSms();

    $customer = portalAccessCustomer(phone: '7195551212');

    $this->post(route('portal.access.challenges.store'), [
        'contact' => '719-555-1212',
    ])->assertRedirect(route('portal.access.verify'))
        ->assertSessionHas('portal_access_notice')
        ->assertSessionHas('portal_access_challenge_id');

    $challenge = PortalAccessChallenge::query()->sole();
    $plainCode = null;

    Http::assertSent(function ($request) use (&$plainCode): bool {
        if (! str_contains($request->url(), 'api.twilio.com')) {
            return false;
        }

        parse_str((string) $request->body(), $body);
        $message = (string) ($body['Body'] ?? '');

        if (preg_match('/(\d{6})/', $message, $matches) !== 1) {
            return false;
        }

        $plainCode = $matches[1];

        return true;
    });

    expect($plainCode)->toMatch('/^\d{6}$/')
        ->and($challenge->customer_id)->toBe($customer->id)
        ->and($challenge->destination)->toBe('7195551212');

    $this->post(route('portal.access.verify.store'), [
        'code' => $plainCode,
    ])->assertRedirect(route('portal.home'));

    $this->get(route('portal.home'))
        ->assertOk()
        ->assertSee('Welcome back')
        ->assertSee('2014 Jeep Wrangler')
        ->assertSee('2021 Subaru Outback');
});

test('customer can access portal with email code', function () {
    Mail::fake();

    $customer = portalAccessCustomer(email: 'molly@example.test');

    $this->post(route('portal.access.challenges.store'), [
        'contact' => 'molly@example.test',
    ])->assertRedirect(route('portal.access.verify'));

    $challenge = PortalAccessChallenge::query()->sole();
    $plainCode = portalAccessPlainCodeFromMailFake();

    Mail::assertSent(PortalAccessCodeMail::class, function (PortalAccessCodeMail $mail) use ($plainCode): bool {
        return $mail->hasTo('molly@example.test')
            && $mail->plainCode === $plainCode;
    });

    $this->post(route('portal.access.verify.store'), [
        'code' => $plainCode,
    ])->assertRedirect(route('portal.home'));
});

test('unknown contact still redirects to verify without revealing absence', function () {
    Mail::fake();

    Http::fake([
        'https://api.twilio.com/*' => Http::response(['sid' => 'SMnone', 'status' => 'queued'], 201),
    ]);
    bindFakeOutboundSms();

    $this->post(route('portal.access.challenges.store'), [
        'contact' => 'unknown@example.test',
    ])->assertRedirect(route('portal.access.verify'))
        ->assertSessionHas('portal_access_notice')
        ->assertSessionMissing('portal_access_challenge_id');

    expect(PortalAccessChallenge::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

test('placeholder easter egg stays on sign in and never sends a code', function (string $contact, string $messageNeedle) {
    Mail::fake();
    Http::fake();

    $expected = \App\Ark\Operations\Portal\PortalAccessEasterEgg::messageFor($contact);
    expect($expected)->not->toBeNull();

    $this->from(route('portal.access'))
        ->post(route('portal.access.challenges.store'), [
            'contact' => $contact,
        ])
        ->assertRedirect(route('portal.access'))
        ->assertSessionHas('portal_access_easter_egg', $expected)
        ->assertSessionMissing('portal_access_challenge_id');

    expect(PortalAccessChallenge::query()->count())->toBe(0);
    Mail::assertNothingSent();
    Http::assertNothingSent();

    $this->get(route('portal.access'))
        ->assertOk()
        ->assertSee($messageNeedle, false)
        ->assertSee($contact, false);
})->with([
    'jenny email' => ['jenny@example.com', 'jukebox'],
    'jenny email mixed case' => ['Jenny@Example.com', 'jukebox'],
    'jenny phone' => ['555-867-5309', 'jukebox'],
    'prior local jenny phone' => ['719-867-5309', 'jukebox'],
    'crash override email' => ['crashoverride@example.com', 'hack the planet'],
    'acid burn email' => ['acidburn@example.com', 'Gibson'],
    'hackers phone' => ['555-555-4202', 'Zero Cool'],
    'venkman email' => ['venkman@example.com', 'who you gonna call'],
    'ghostbusters phone' => ['555-555-2368', 'Ghostbusters hotline'],
    'ferris email' => ['ferris@example.com', 'personal day'],
    'bueller phone' => ['555-555-2383', 'not coming in'],
    'neo email' => ['neo@example.com', 'no code'],
    'matrix phone' => ['555-555-0690', 'callback number'],
]);

test('invalid code does not create portal session', function () {
    Mail::fake();

    portalAccessCustomer(email: 'verify@example.test');

    $this->post(route('portal.access.challenges.store'), [
        'contact' => 'verify@example.test',
    ])->assertRedirect(route('portal.access.verify'));

    $this->post(route('portal.access.verify.store'), [
        'code' => '000000',
    ])->assertRedirect()
        ->assertSessionHasErrors('code');

    $this->get(route('portal.home'))->assertRedirect(route('portal.access'));
});

test('wrong customer cannot access another customers vehicles through session guard', function () {
    Mail::fake();

    $owner = portalAccessCustomer(email: 'owner@example.test');
    $other = Customer::query()->create([
        'first_name' => 'Other',
        'last_name' => 'Customer',
        'phone' => '7195559999',
        'email' => 'other@example.test',
    ]);

    $this->post(route('portal.access.challenges.store'), [
        'contact' => 'owner@example.test',
    ]);

    $challenge = PortalAccessChallenge::query()->sole();
    $plainCode = portalAccessPlainCodeFromMailFake();

    $this->post(route('portal.access.verify.store'), [
        'code' => $plainCode,
    ])->assertRedirect(route('portal.home'));

    $this->get(route('portal.home'))
        ->assertOk()
        ->assertSee($owner->first_name)
        ->assertDontSee('Other Customer');

    expect($other->id)->not->toBe($owner->id);
});

function portalAccessCustomer(?string $phone = null, ?string $email = null): Customer
{
    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Customer',
        'phone' => $phone ?? '7195550100',
        'email' => $email ?? 'molly.customer@example.test',
    ]);

    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Jeep',
        'model' => 'Wrangler',
        'plate' => 'JEEP14',
    ]);

    Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2021,
        'make' => 'Subaru',
        'model' => 'Outback',
        'plate' => 'SUB21',
    ]);

    return $customer;
}

function portalAccessPlainCodeFromMailFake(): string
{
    $sent = Mail::sent(PortalAccessCodeMail::class)->first();

    expect($sent)->not->toBeNull();

    return $sent->plainCode;
}
