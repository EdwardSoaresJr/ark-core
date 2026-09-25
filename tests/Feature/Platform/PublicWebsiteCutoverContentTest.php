<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\Public\PublicAppointmentRequest;
use App\Ark\Operations\PhoneVerification\PhoneVerification;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Mail\BookIdentityCodeMail;
use App\Ark\Website\Catalog\PublicWebsiteCatalog;
use App\Ark\Website\PublishWebsiteCatalog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

function publishCutoverSite(): void
{
    config([
        'mail.from.address' => null,
        'website.custom_domains' => [[
            'domain' => 'lugsnplugs.com',
            'site_host' => 'lugsnplugs.arksms.com',
            'preferred' => true,
        ]],
    ]);

    app(PublishWebsiteCatalog::class)->publish('lugsnplugs.arksms.com', PublicWebsiteCatalog::document(), true);
}

function bookPayload(array $overrides = []): array
{
    $date = PublicAppointmentRequest::availabilityProjection()['dates'][0]['date'] ?? null;

    return array_merge([
        'concern_category' => 'Check Engine Light',
        'concern_details' => 'Light came on yesterday.',
        'vehicle_year' => 2016,
        'vehicle_make' => 'Honda',
        'vehicle_model' => 'Civic',
        'preferred_date' => $date,
        'preferred_period' => 'morning',
        'contact_name' => 'Pat Driver',
        'contact_phone' => '7195550142',
        'contact_email' => 'pat@example.test',
        'contact_preference' => 'text',
        'page' => 'book',
    ], $overrides);
}

test('appointment request from a new customer reaches the core lead recorder', function (): void {
    publishCutoverSite();
    Http::fake();
    Mail::fake();

    $this->get('http://lugsnplugs.arksms.com/book?concern=Brakes')
        ->assertOk()
        ->assertSee('Request an appointment')
        ->assertSee('does not reserve a bay')
        ->assertSee('Check Engine Light')
        ->assertSee('Morning')
        ->assertSee('Afternoon')
        ->assertSee('Flexible')
        ->assertSee('Text me')
        ->assertSee('value="Brakes"', false);

    $this->post('http://lugsnplugs.arksms.com/leads', bookPayload([
        'concern_category' => 'Brakes',
        'concern_details' => 'Grinding from the front.',
    ]))->assertRedirect();

    $lead = Lead::query()->first();
    expect($lead)->not->toBeNull()
        ->and($lead->source)->toBe(LeadSource::Website)
        ->and($lead->contact_preference)->toBe(LeadContactPreference::Text)
        ->and($lead->vehicle_year)->toBe(2016)
        ->and($lead->vehicle_make)->toBe('Honda')
        ->and($lead->concern)->toContain('Brakes')
        ->and($lead->concern)->toContain('Preferred visit:')
        ->and($lead->metadata['appointment_request']['preferred_period'] ?? null)->toBe('morning')
        ->and($lead->metadata['canonical_host'] ?? null)->toBe('lugsnplugs.com')
        ->and(Appointment::query()->count())->toBe(0);

    Http::assertNothingSent();
});

test('a recognized customer can request service for an existing vehicle', function (): void {
    publishCutoverSite();
    Mail::fake();

    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Driver',
        'phone' => '7195550142',
        'email' => 'molly@example.test',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $this->actingAs($customer, 'portal')
        ->get('http://lugsnplugs.arksms.com/book')
        ->assertOk()
        ->assertSee('Camry');

    $this->actingAs($customer, 'portal')
        ->post('http://lugsnplugs.arksms.com/leads', bookPayload([
            'vehicle_selection' => (string) $vehicle->id,
            'contact_name' => 'Molly Driver',
        ]))
        ->assertRedirect();

    $lead = Lead::query()->first();
    expect($lead)->not->toBeNull()
        ->and($lead->vehicle_id)->toBe($vehicle->id)
        ->and($lead->customer_id)->toBe($customer->id)
        ->and($lead->vehicle_model)->toBe('Camry');
});

test('paused appointment requests do not create a lead', function (): void {
    publishCutoverSite();
    $settings = ShopSettings::current();
    $weekly = [];
    foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
        $weekly[$day] = ['enabled' => false];
    }
    $settings->update([
        'appointment_request_availability' => [
            'weekly' => $weekly,
            'horizon_days' => 14,
            'minimum_notice_days' => 0,
        ],
    ]);
    ShopSettings::forgetCurrent();

    $this->get('http://lugsnplugs.arksms.com/book')
        ->assertOk()
        ->assertSee('Online appointment requests are paused')
        ->assertDontSee('Send appointment request');

    $this->post('http://lugsnplugs.arksms.com/leads', bookPayload())
        ->assertSessionHasErrors('preferred_date');

    expect(Lead::query()->count())->toBe(0);
});

test('book verification is required when the phone gate is ready and does not send when it is not', function (): void {
    publishCutoverSite();
    Http::fake();
    Mail::fake();

    $this->post('http://lugsnplugs.arksms.com/leads', bookPayload([
        'book_intent' => 'send_phone_code',
    ]))->assertSessionHasErrors('contact_phone');

    expect(Lead::query()->count())->toBe(0);
    Http::assertNothingSent();

    $this->post('http://lugsnplugs.arksms.com/leads', bookPayload([
        'book_intent' => 'send_email_code',
    ]))->assertSessionHasErrors('contact_email');

    Mail::assertNothingSent();

    $customer = Customer::query()->create([
        'first_name' => 'Molly',
        'last_name' => 'Driver',
        'phone' => '7195550142',
        'email' => 'molly@example.test',
    ]);
    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2018,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    ShopSettings::current()->update(['telephony_inbound_number' => '7195559999']);
    ShopSettings::forgetCurrent();
    $transport = bindFakeOutboundSms();

    $this->post('http://lugsnplugs.arksms.com/leads', bookPayload())
        ->assertSessionHasErrors('contact_phone');

    expect(Lead::query()->count())->toBe(0)
        ->and($transport->sent)->toBe([]);

    $this->post('http://lugsnplugs.arksms.com/leads', bookPayload([
        'book_intent' => 'send_phone_code',
    ]))->assertSessionHas('book_status', 'Verification code sent.');

    expect(Lead::query()->count())->toBe(0)
        ->and($transport->sent)->toHaveCount(1);

    PhoneVerification::query()->latest('id')->first()->forceFill([
        'code_hash' => PhoneVerification::hashCode('483291'),
    ])->save();

    $this->post('http://lugsnplugs.arksms.com/leads', bookPayload([
        'book_intent' => 'check_phone_code',
        'phone_code' => '483291',
    ]))->assertSessionHas('book_status', 'Phone verified.');

    $this->get('http://lugsnplugs.arksms.com/book')
        ->assertOk()
        ->assertSee('Camry')
        ->assertSee('Text me a code');

    $this->post('http://lugsnplugs.arksms.com/leads', bookPayload([
        'vehicle_selection' => (string) $vehicle->id,
        'contact_name' => 'Molly Driver',
    ]))->assertRedirect();

    $lead = Lead::query()->first();
    expect($lead)->not->toBeNull()
        ->and($lead->customer_id)->toBe($customer->id)
        ->and($lead->vehicle_id)->toBe($vehicle->id);

    auth('portal')->logout();

    $this->post('http://lugsnplugs.arksms.com/leads', bookPayload([
        'book_intent' => 'send_phone_code',
        'contact_phone' => '7195550188',
        'contact_name' => 'Pat Driver',
    ]))->assertSessionHas('book_status', 'Verification code sent.');

    PhoneVerification::query()->latest('id')->first()->forceFill([
        'code_hash' => PhoneVerification::hashCode('111111'),
    ])->save();

    $this->post('http://lugsnplugs.arksms.com/leads', bookPayload([
        'book_intent' => 'check_phone_code',
        'phone_code' => '111111',
        'contact_phone' => '7195550188',
        'contact_name' => 'Pat Driver',
    ]))->assertSessionHas('book_status', 'Phone verified.');

    $this->post('http://lugsnplugs.arksms.com/leads', bookPayload([
        'contact_phone' => '7195550188',
        'contact_name' => 'Pat Driver',
    ]))->assertRedirect();

    $guest = Lead::query()->latest('id')->first();
    expect($guest)->not->toBeNull()
        ->and($guest->customer_id)->toBeNull()
        ->and($guest->contact_name)->toBe('Pat Driver')
        ->and($guest->source)->toBe(LeadSource::Website);

    config(['mail.from.address' => 'shop@example.test']);

    $this->post('http://lugsnplugs.arksms.com/leads', bookPayload([
        'book_intent' => 'send_email_code',
        'contact_email' => 'pat@example.test',
        'contact_phone' => '7195550199',
    ]))->assertSessionHas('book_status', 'Verification code sent.');

    Mail::assertSent(BookIdentityCodeMail::class);
});

test('indexed code pages and repairpal pages are on the canonical sitemap', function (): void {
    publishCutoverSite();
    Http::fake();

    $codes = [
        'p0016', 'p0101', 'p0128', 'p0135', 'p0174', 'p0300', 'p0301', 'p0302',
        'p0303', 'p0304', 'p0340', 'p0401', 'p0420', 'p0430', 'p0442', 'p0455',
    ];

    foreach ($codes as $code) {
        $this->get('http://lugsnplugs.com/common-problems/'.$code)
            ->assertOk()
            ->assertSee(strtoupper($code), false);
    }

    $this->get('http://lugsnplugs.com/repairpal')->assertOk()->assertSee('RepairPal Certified');
    $this->get('http://lugsnplugs.com/repairpal-certified')->assertOk()->assertSee('What RepairPal Certified means');
    $this->get('http://lugsnplugs.com/repairpal-reviews')->assertOk()->assertSee('Google reviews still matter');
    $this->get('http://lugsnplugs.com/repairpal-warranty')
        ->assertOk()
        ->assertSee('12 months / 12,000 miles')
        ->assertSee('24 months / 24,000 miles');
    $this->get('http://lugsnplugs.com/warranty')
        ->assertOk()
        ->assertSee('24 months / 24,000 miles');

    $sitemap = $this->get('http://lugsnplugs.arksms.com/sitemap.xml')->assertOk();
    foreach ($codes as $code) {
        $sitemap->assertSee('https://lugsnplugs.com/common-problems/'.$code, false);
    }
    $sitemap->assertSee('https://lugsnplugs.com/repairpal-warranty', false)
        ->assertDontSee('lugsnplugs.arksms.com', false);

    Http::assertNothingSent();
});
