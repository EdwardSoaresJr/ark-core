<?php

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Leads\Public\ContactPageProjection;
use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update([
        'learn_training_gate_enabled' => false,
        'address_line_1' => '100 Main Street',
        'address_line_2' => 'Unit D',
        'city' => 'Colorado Springs',
        'state' => 'CO',
        'postal_code' => '80909',
        'phone' => '7194136227',
        'email' => 'hello@demo-auto.test',
    ]);
});

test('public contact hub is a dedicated destination with nap hours form and faqs', function (): void {
    $response = $this->get(route('public.contact'))
        ->assertOk()
        ->assertSee('Contact us', false)
        ->assertSee('Contact LugsNPlugs', false)
        ->assertSee('(719) 413-6227', false)
        ->assertSee('100 Main Street', false)
        ->assertSee('Colorado Springs', false)
        ->assertSee('80909', false)
        ->assertSee('Need help with your vehicle?', false)
        ->assertSee('Book an Appointment', false)
        ->assertSee('Do I need an appointment?', false)
        ->assertSee('hello@demo-auto.test', false)
        ->assertSee('id="send-a-message"', false)
        ->assertSee('name="subject"', false)
        ->assertSee('name="message"', false)
        ->assertDontSee('name="vehicle_year"', false)
        ->assertDontSee('name="preferred_date"', false)
        ->assertDontSee('id="concern"', false)
        ->assertSee('<meta property="og:title"', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('Contact', false);

    expect($response->getContent())->toContain('customer-page-split--public')
        ->and($response->getContent())->toContain('customer-page-split__rail')
        ->and($response->getContent())->toContain('Ways to reach us');

    expect(ContactPageProjection::forDisplay()['faqs'])->not->toBeEmpty();
});

test('contact page is linked from customer navigation', function (): void {
    $this->get(route('public.home'))
        ->assertOk()
        ->assertSee(route('public.contact'), false)
        ->assertSee('customer-header__nav', false)
        ->assertSeeText('Contact')
        ->assertSee('Book an Appointment', false)
        ->assertDontSee('>Book</a>', false);
});

test('legacy contact-us redirects to the contact hub', function (): void {
    $this->get('/contact-us')
        ->assertRedirect('/contact');
});

test('sitemap lists the contact hub', function (): void {
    $this->get(route('sitemap.xml'))
        ->assertOk()
        ->assertSee('/contact', false);
});

test('contact inquiry form posts general message without vehicle fields', function (): void {
    $this->post(route('public.leads.store'), [
        'contact_inquiry' => '1',
        'name' => 'Alex Rivera',
        'email' => 'alex@example.test',
        'phone' => '',
        'subject' => 'Billing question',
        'message' => 'Can you resend last month’s invoice?',
        'source' => LeadSource::Website->value,
        'form_rendered_at' => now()->subSeconds(5)->timestamp,
        'public_surface_page' => 'contact',
        'public_surface_variant' => 'contact_inquiry',
        'public_surface_placement' => 'embedded_form',
    ])->assertRedirect(route('public.leads.thanks'));

    $lead = Lead::query()->sole();

    expect($lead->concern)->toStartWith('Subject: Billing question')
        ->and($lead->concern)->toContain('resend last month')
        ->and($lead->contact_email)->toBe('alex@example.test')
        ->and($lead->contact_phone)->toBeNull()
        ->and($lead->vehicle_year)->toBeNull()
        ->and(data_get($lead->metadata, 'contact_inquiry.subject'))->toBe('Billing question')
        ->and(data_get($lead->metadata, 'public_surface.variant'))->toBe('contact_inquiry');
});

test('website manage persists contact visit notes and faqs', function (): void {
    $current = PublicSurfaceSettings::current();

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->withSession([\App\Ark\Operations\Workstations\WorkstationPresence::SESSION_BIND_DISMISSED => true])
        ->patch(route('website.manage.update'), [
            'google_rating' => $current['google_rating'],
            'google_review_count' => $current['google_review_count'],
            'google_reviews_url' => $current['google_reviews_url'],
            'contact_visit_notes' => 'Visitor parking is in front of Unit D.',
            'contact_faqs' => [
                [
                    'question' => 'Do you offer loaner cars?',
                    'answer' => 'Not currently — ask about shuttle options when scheduling.',
                ],
                [
                    'question' => '',
                    'answer' => '',
                ],
            ],
            'photo_alt' => ['Bay', 'Scan', 'Lift', 'Tech'],
        ])
        ->assertRedirect(route('website.manage'));

    expect(PublicSurfaceSettings::current()['contact_visit_notes'])->toBe('Visitor parking is in front of Unit D.')
        ->and(PublicSurfaceSettings::current()['contact_faqs'])->toHaveCount(1)
        ->and(PublicSurfaceSettings::current()['contact_faqs'][0]['question'])->toBe('Do you offer loaner cars?');

    $this->get(route('public.contact'))
        ->assertOk()
        ->assertSee('Visitor parking is in front of Unit D.', false)
        ->assertSee('Do you offer loaner cars?', false);
});
