<?php

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\Public\PublicAppointmentRequest;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Website\Catalog\PublicWebsiteCatalog;
use App\Ark\Website\PublishWebsiteCatalog;
use App\Ark\Website\WebsitePublicationCatalogMerge;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * A shop whose website is not the imported catalog: its own name, story, and lenders only.
 */
function publishOtherShopWebsite(array $overrides = []): void
{
    ShopSettings::current()->update([
        'shop_name' => 'Example Auto Works',
        'phone' => '5550100199',
        'email' => 'hello@example.test',
        'address_line_1' => '100 Example Way',
        'city' => 'Denver',
        'state' => 'CO',
        'postal_code' => '80202',
    ]);
    ShopSettings::forgetCurrent();

    $document = PublicWebsiteCatalog::document();
    $document['source'] = 'example-shop';
    unset($document['about_story'], $document['pages']['repairpal'], $document['pages']['repairpal-certified'],
        $document['pages']['repairpal-reviews'], $document['pages']['repairpal-warranty']);
    $document['financing']['programs'] = [
        ['name' => 'Wisetack', 'body' => 'Pay over time.', 'url' => ''],
        ['name' => 'Synchrony Car Care', 'body' => 'A card for car care.', 'url' => 'https://www.synchrony.com/financing/car-care/prospecting'],
    ];
    $document['trust_signals'] = ['financing_available' => true];
    $document['contact_faqs'] = [
        ['question' => 'What forms of payment do you accept?', 'answer' => 'Cards and cash.'],
    ];

    app(PublishWebsiteCatalog::class)->publish('example.arksms.com', array_replace_recursive($document, $overrides), true);
    URL::forceScheme('https');
}

function otherShopBookPayload(): array
{
    return [
        'concern_category' => 'Check Engine Light',
        'concern_details' => 'Light came on yesterday.',
        'vehicle_year' => 2016,
        'vehicle_make' => 'Honda',
        'vehicle_model' => 'Civic',
        'preferred_date' => PublicAppointmentRequest::availabilityProjection()['dates'][0]['date'] ?? null,
        'preferred_period' => 'morning',
        'contact_name' => 'Pat Driver',
        'contact_phone' => '7195550142',
        'contact_email' => 'pat@example.test',
        'contact_preference' => 'text',
        'page' => 'book',
    ];
}

test('another shop never inherits the catalog business merchant links, answers, or story', function (): void {
    publishOtherShopWebsite();

    $this->get('http://example.arksms.com/financing')
        ->assertOk()
        ->assertSee('https://www.synchrony.com/financing/car-care/prospecting', false)
        ->assertDontSee('CR243778456', false)
        ->assertDontSee('uz8sh8e', false);

    $this->get('http://example.arksms.com/contact')
        ->assertOk()
        ->assertSee('Cards and cash.')
        ->assertDontSee('Wisetack', false);

    foreach (['/', '/about', '/services', '/services/brakes'] as $path) {
        $this->get('http://example.arksms.com'.$path)
            ->assertOk()
            ->assertSee('Example Auto Works')
            ->assertDontSee('owned and operated by Edward and Molly Soares', false)
            ->assertDontSee('LugsNPlugs Automotive team', false)
            ->assertDontSee('LugsNPlugs diagnoses', false)
            ->assertDontSee('At LugsNPlugs', false)
            ->assertDontSee('About LugsNPlugs', false)
            ->assertDontSee('RepairPal Certified coverage', false);
    }

    $this->get('http://example.arksms.com/about')
        ->assertSee('Example Auto Works is a repair shop built around a simple idea', false);
});

test('a shop story comes from its own document', function (): void {
    publishOtherShopWebsite(['about_story' => 'Example Auto Works has served Denver drivers since 1998.']);

    $this->get('http://example.arksms.com/')->assertOk()->assertSee('Example Auto Works has served Denver drivers since 1998.');
});

test('the catalog publication keeps its own story, answers, and merchant link', function (): void {
    ShopSettings::current()->update(['shop_name' => 'LugsNPlugs Automotive']);
    ShopSettings::forgetCurrent();
    $document = PublicWebsiteCatalog::document();
    unset($document['about_story']);
    $document['trust_signals'] = ['financing_available' => true];
    $document['financing']['programs'][1]['url'] = 'https://www.synchrony.com/financing/car-care/prospecting';
    app(PublishWebsiteCatalog::class)->publish('lugsnplugs.arksms.com', $document, true);
    URL::forceScheme('https');

    $this->get('http://lugsnplugs.arksms.com/')->assertOk()
        ->assertSee('LugsNPlugs Automotive is owned and operated by Edward and Molly Soares.', false)
        ->assertSee('RepairPal Certified coverage', false);
    $this->get('http://lugsnplugs.arksms.com/financing')->assertOk()
        ->assertSee('https://www.synchrony.com/mmc/CR243778456?sitecode=acewel401', false);
});

test('a catalog merge keeps the shop own financing', function (): void {
    $merged = app(WebsitePublicationCatalogMerge::class)->merge(
        ['financing' => ['programs' => [['name' => 'Own lender', 'url' => 'https://lender.example.test']]]],
        PublicWebsiteCatalog::document(),
    );

    expect($merged['financing']['programs'][0]['name'])->toBe('Own lender');
});

test('a public notice shows only when configured', function (): void {
    publishOtherShopWebsite();

    $this->get('http://example.arksms.com/')->assertOk()->assertDontSee('ark-public-notice', false);
    $this->get('http://example.arksms.com/app/login')->assertDontSee('ark-public-notice', false);

    config(['website.public_notice' => ['text' => 'Demonstration shop.', 'link_label' => 'Enter', 'link_url' => '/demo']]);

    $this->get('http://example.arksms.com/')->assertOk()->assertSee('Demonstration shop.')->assertSee('href="/demo"', false);
    $this->get('http://example.arksms.com/book')->assertOk()->assertSee('Demonstration shop.');
});

test('a code is offered only when the shop can actually send one', function (): void {
    publishOtherShopWebsite();
    Mail::fake();
    config(['mail.from.address' => 'shop@example.test']);

    // A from address alone sends nothing: without managed mail, no email code is offered.
    $this->get('http://example.arksms.com/book')->assertOk()->assertDontSee('Email me a code', false);
    Mail::assertNothingOutgoing();
});

test('with identity verification off a request is recorded unverified and no code is offered', function (): void {
    publishOtherShopWebsite();
    Mail::fake();
    config(['mail.from.address' => 'shop@example.test', 'website.book_identity_verification' => false]);

    $this->get('http://example.arksms.com/book')->assertOk()->assertDontSee('Email me a code', false);
    $this->post('http://example.arksms.com/leads', otherShopBookPayload())
        ->assertRedirect(route('public.leads.thanks'));

    expect(Lead::query()->count())->toBe(1);
    Mail::assertNothingOutgoing();
});
