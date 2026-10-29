<?php

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadFormRenderStamp;
use App\Ark\Operations\Leads\LeadState;
use App\Ark\Operations\Leads\Public\PublicAppointmentRequest;
use App\Ark\Operations\PhoneVerification\PhoneVerification;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Website\Catalog\PublicWebsiteCatalog;
use App\Ark\Website\PublishWebsiteCatalog;
use Illuminate\Support\Facades\Mail;

function publishMeasuredAppointmentSite(array $measurement = []): void
{
    config([
        'mail.from.address' => null,
        'services.twilio.account_sid' => null,
        'services.twilio.auth_token' => null,
        'website.custom_domains' => [[
            'domain' => 'lugsnplugs.com',
            'site_host' => 'lugsnplugs.arksms.com',
            'preferred' => true,
        ]],
        'ark-appointment-request-measurement.ga4_measurement_id' => 'G-HR9MP6ENEP',
        'ark-appointment-request-measurement.ads_id' => 'AW-17485686048',
        'ark-appointment-request-measurement.ads_conversion_label' => '',
        'ark-appointment-request-measurement.linker_domains' => 'lugsnplugs.com',
        ...$measurement,
    ]);

    app(PublishWebsiteCatalog::class)->publish('lugsnplugs.arksms.com', PublicWebsiteCatalog::document(), true);
}

function measuredBookPayload(array $overrides = []): array
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

test('an unverified appointment request does not emit a lead conversion', function (): void {
    publishMeasuredAppointmentSite();
    Mail::fake();
    ShopSettings::current()->update(['telephony_inbound_number' => null]);
    ShopSettings::forgetCurrent();

    $this->get('http://lugsnplugs.arksms.com/book?gclid=Cj0KEShouldNotCount')
        ->assertOk()
        ->assertSee('G-HR9MP6ENEP', false)
        ->assertDontSee('generate_lead', false);

    $this->post('http://lugsnplugs.arksms.com/leads', measuredBookPayload())
        ->assertRedirect();

    $lead = Lead::query()->first();
    expect($lead)->not->toBeNull()
        ->and($lead->metadata['measurement'] ?? null)->toBeNull();

    $this->get('http://lugsnplugs.arksms.com/leads/thanks')
        ->assertOk()
        ->assertDontSee('generate_lead', false)
        ->assertDontSee('appointment_request', false);
});

test('a verified appointment request emits one lead conversion and a refresh does not repeat it', function (): void {
    publishMeasuredAppointmentSite();
    Mail::fake();
    config([
        'services.twilio.account_sid' => 'AC00000000000000000000000000000000',
        'services.twilio.auth_token' => 'test-token',
    ]);
    ShopSettings::current()->update(['telephony_inbound_number' => '7195559999']);
    ShopSettings::forgetCurrent();
    bindFakeOutboundSms();

    $this->get('http://lugsnplugs.arksms.com/book?gclid=Cj0KEVerifiedClick&utm_source=google&utm_medium=cpc&email=hidden@example.test')
        ->assertOk();

    $this->post('http://lugsnplugs.arksms.com/leads', measuredBookPayload([
        'book_intent' => 'send_phone_code',
    ]))->assertSessionHas('book_status', 'Verification code sent.');

    PhoneVerification::query()->latest('id')->first()->forceFill([
        'code_hash' => PhoneVerification::hashCode('483291'),
    ])->save();

    $this->post('http://lugsnplugs.arksms.com/leads', measuredBookPayload([
        'book_intent' => 'check_phone_code',
        'phone_code' => '483291',
    ]))->assertSessionHas('book_status', 'Phone verified.');

    $this->post('http://lugsnplugs.arksms.com/leads', measuredBookPayload())
        ->assertRedirect();

    $lead = Lead::query()->first();
    expect($lead)->not->toBeNull()
        ->and($lead->state)->toBe(LeadState::Received)
        ->and($lead->metadata['measurement']['lead_type'] ?? null)->toBe('appointment_request')
        ->and($lead->metadata['measurement']['attribution']['gclid'] ?? null)->toBe('Cj0KEVerifiedClick')
        ->and($lead->metadata['measurement']['attribution']['utm_source'] ?? null)->toBe('google')
        ->and($lead->metadata['measurement']['attribution'])->not->toHaveKey('email');

    $eventId = $lead->metadata['measurement']['event_id'];

    $first = $this->get('http://lugsnplugs.arksms.com/leads/thanks')
        ->assertOk()
        ->assertSee('generate_lead', false)
        ->assertSee('appointment_request', false)
        ->assertSee($eventId, false)
        ->assertSee('Cj0KEVerifiedClick', false)
        ->assertDontSee('send_to', false)
        ->assertDontSee('hidden@example.test', false);

    expect(substr_count($first->getContent(), 'generate_lead'))->toBe(1);

    $lead->refresh();
    expect($lead->metadata['measurement']['emitted_at'] ?? null)->not->toBeNull();

    $this->get('http://lugsnplugs.arksms.com/leads/thanks')
        ->assertOk()
        ->assertDontSee('generate_lead', false)
        ->assertDontSee($eventId, false);
});

test('google ads receives the lead conversion only when a lead label is configured', function (): void {
    publishMeasuredAppointmentSite([
        'ark-appointment-request-measurement.ads_conversion_label' => 'appointmentRequestLead',
    ]);
    Mail::fake();
    config([
        'services.twilio.account_sid' => 'AC00000000000000000000000000000000',
        'services.twilio.auth_token' => 'test-token',
    ]);
    ShopSettings::current()->update(['telephony_inbound_number' => '7195559999']);
    ShopSettings::forgetCurrent();
    bindFakeOutboundSms();

    $this->post('http://lugsnplugs.arksms.com/leads', measuredBookPayload([
        'book_intent' => 'send_phone_code',
        'contact_phone' => '7195550188',
    ]));

    PhoneVerification::query()->latest('id')->first()->forceFill([
        'code_hash' => PhoneVerification::hashCode('483291'),
    ])->save();

    $this->post('http://lugsnplugs.arksms.com/leads', measuredBookPayload([
        'book_intent' => 'check_phone_code',
        'phone_code' => '483291',
        'contact_phone' => '7195550188',
    ]))->assertSessionHas('book_status');

    $this->post('http://lugsnplugs.arksms.com/leads', measuredBookPayload([
        'contact_phone' => '7195550188',
    ]))->assertRedirect();

    $this->get('http://lugsnplugs.arksms.com/leads/thanks')
        ->assertOk()
        ->assertSee('generate_lead', false)
        ->assertSee('send_to', false)
        ->assertSee('AW-17485686048\/appointmentRequestLead', false)
        ->assertDontSee('purchase', false);
});

test('a contact message and the honeypot thanks page are not appointment conversions', function (): void {
    publishMeasuredAppointmentSite();
    Mail::fake();

    $this->post('http://lugsnplugs.arksms.com/leads', [
        'company_website' => 'https://spam.example',
        'contact_name' => 'Spam',
        'contact_phone' => '7195550142',
        'concern' => 'Buy links',
    ])->assertRedirect();

    expect(Lead::query()->count())->toBe(0);
    $this->get('http://lugsnplugs.arksms.com/leads/thanks')
        ->assertOk()
        ->assertDontSee('generate_lead', false);

    $this->post('http://lugsnplugs.arksms.com/leads', [
        'contact_name' => 'Pat Driver',
        'contact_phone' => '7195550142',
        'concern' => 'What are your hours?',
        'form_rendered_at' => LeadFormRenderStamp::issue(now()->subSeconds(5)),
        'page' => 'contact',
    ])->assertRedirect();

    expect(Lead::query()->count())->toBe(1)
        ->and(Lead::query()->first()->metadata['measurement'] ?? null)->toBeNull();

    $this->get('http://lugsnplugs.arksms.com/leads/thanks')
        ->assertOk()
        ->assertDontSee('generate_lead', false);
});

test('measurement stays off when no measurement id is configured', function (): void {
    publishMeasuredAppointmentSite([
        'ark-appointment-request-measurement.ga4_measurement_id' => '',
        'ark-appointment-request-measurement.ads_id' => '',
    ]);
    Mail::fake();

    $this->get('http://lugsnplugs.arksms.com/book')
        ->assertOk()
        ->assertDontSee('googletagmanager.com/gtag/js', false)
        ->assertDontSee('generate_lead', false);
});
