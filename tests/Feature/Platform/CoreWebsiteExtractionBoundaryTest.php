<?php

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Website\Catalog\PublicWebsiteCatalog;
use App\Ark\Website\PublishWebsiteCatalog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

function publishLocalWebsite(): void
{
    $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

    app(PublishWebsiteCatalog::class)->publish($host, [
        'headline' => 'Accurate Diagnostics. Honest Repairs.',
        'positioning_lede' => 'We find the real problem first.',
        'local_tagline' => 'Family owned in Colorado Springs.',
        'google_rating' => '4.9',
        'google_review_count' => 56,
        'reviews' => [
            ['quote' => 'Edward is an amazing mechanic.', 'attribution' => 'Eric'],
        ],
        'contact_faqs' => [
            ['question' => 'Do I need an appointment?', 'answer' => 'Appointments help us save bay time.'],
        ],
        'seo' => [
            'home' => ['title' => 'Auto Repair Colorado Springs', 'description' => 'Testing before parts.'],
            'book' => ['title' => 'Book an Appointment', 'description' => 'Request an appointment.'],
            'contact' => ['title' => 'Contact', 'description' => 'Call or send a message.'],
            'financing' => ['title' => 'Financing', 'description' => 'Pay over time when the job qualifies.'],
            'warranty' => ['title' => 'Repair Warranty', 'description' => '24 month shop warranty.'],
            'privacy' => ['title' => 'Privacy policy', 'description' => 'What we collect.'],
            'terms' => ['title' => 'Terms of use', 'description' => 'Basic terms.'],
            'common_problems' => ['title' => 'Common problems', 'description' => 'Symptom pages.'],
        ],
        'pages' => [
            'warranty' => ['title' => 'Repair warranty', 'lede' => '24 months / 24,000 miles.', 'sections' => []],
            'privacy' => ['title' => 'Privacy policy', 'lede' => 'We do not sell your personal information.', 'sections' => []],
            'terms' => ['title' => 'Terms of use', 'lede' => 'Submitting the form does not reserve a bay.', 'sections' => []],
        ],
        'financing' => [
            'lede' => 'If the job qualifies, you can pay over time.',
            'programs' => [],
        ],
        'common_problems' => [[
            'slug' => 'check-engine-light',
            'title' => 'Check Engine Light',
            'tier' => 1,
            'problem' => 'The computer saw something outside its normal range.',
            'meta_description' => 'Check engine light diagnosis.',
            'symptoms' => ['Solid check engine light'],
            'can_drive' => ['Drive carefully to the shop.'],
            'common_causes' => ['Loose gas cap'],
            'what_happens_next' => ['We scan the car.'],
        ]],
    ], force: true);
}

test('guest homepage renders the published site and does not redirect to staff login', function (): void {
    publishLocalWebsite();

    $this->get('/')
        ->assertOk()
        ->assertSee('Accurate Diagnostics. Honest Repairs.')
        ->assertSee('data-public-surface-theme-toggle', false)
        ->assertSee('rel="canonical"', false);
});

test('unpublished homepage is not the staff login', function (): void {
    $this->get('/')
        ->assertNotFound()
        ->assertSee('This website is not published.');
});

test('core public routes are registered and growth admin routes stay absent', function (): void {
    expect(Route::has('public.home'))->toBeTrue()
        ->and(Route::has('public.book'))->toBeTrue()
        ->and(Route::has('public.contact'))->toBeTrue()
        ->and(Route::has('public.leads.store'))->toBeTrue()
        ->and(Route::has('public.robots'))->toBeTrue()
        ->and(Route::has('public.sitemap'))->toBeTrue()
        ->and(Route::has('growth.opportunities.index'))->toBeFalse()
        ->and(Route::has('website.manage'))->toBeFalse()
        ->and(Route::has('website.performance'))->toBeFalse();
});

test('portal access remains registered', function (): void {
    expect(Route::has('portal.access'))->toBeTrue()
        ->and(Route::has('portal.home'))->toBeTrue();
});

test('shop settings does not expose website marketing configuration', function (): void {
    $fillable = (new ShopSettings)->getFillable();

    expect($fillable)->not->toContain('public_surface_settings')
        ->and($fillable)->not->toContain('growth_integrations')
        ->and($fillable)->not->toContain('growth_google_service_account')
        ->and($fillable)->toContain('google_reviews_url')
        ->and($fillable)->toContain('shop_name')
        ->and($fillable)->toContain('website')
        ->and($fillable)->toContain('shop_timezone')
        ->and($fillable)->toContain('scheduling_hours');

    expect(Schema::hasColumn('shop_settings', 'public_surface_settings'))->toBeFalse()
        ->and(Schema::hasColumn('shop_settings', 'growth_integrations'))->toBeFalse()
        ->and(Schema::hasColumn('shop_settings', 'growth_google_service_account'))->toBeFalse()
        ->and(Schema::hasColumn('shop_settings', 'google_reviews_url'))->toBeTrue();

    expect(Schema::hasTable('growth_contents'))->toBeFalse()
        ->and(Schema::hasTable('growth_sessions'))->toBeFalse()
        ->and(Schema::hasTable('growth_opportunities'))->toBeFalse()
        ->and(Schema::hasTable('public_surface_events'))->toBeFalse();

    expect(Schema::hasColumn('leads', 'growth_session_id'))->toBeFalse()
        ->and(Schema::hasColumn('conversations', 'growth_session_id'))->toBeFalse()
        ->and(Schema::hasColumn('repair_orders', 'growth_session_id'))->toBeFalse();
});

test('public pages, robots, and sitemap render from the publication', function (): void {
    publishLocalWebsite();
    Http::fake();

    $this->get('/book')->assertOk()->assertSee('Book an appointment');
    $this->get('/contact')->assertOk()->assertSee('Do I need an appointment?');
    $this->get('/common-problems')->assertOk()->assertSee('Check Engine Light');
    $this->get('/common-problems/check-engine-light')->assertOk()->assertSee('Loose gas cap');
    $this->get('/financing')->assertOk()->assertSee('pay over time');
    $this->get('/warranty')->assertOk()->assertSee('24 months');
    $this->get('/privacy')->assertOk()->assertSee('Privacy policy');
    $this->get('/terms')->assertOk()->assertSee('Terms of use');
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap:')
        ->assertSee('Disallow: /app/');
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('/common-problems/check-engine-light', false)
        ->assertSee('/book', false);

    Http::assertNothingSent();
});

test('lead form writes a core website lead', function (): void {
    publishLocalWebsite();

    $this->post('/leads', [
        'contact_name' => 'Pat Driver',
        'contact_phone' => '7195550142',
        'concern' => 'Check engine light is on.',
        'page' => 'book',
    ])->assertRedirect(route('public.leads.thanks'));

    $lead = Lead::query()->first();
    expect($lead)->not->toBeNull()
        ->and($lead->source)->toBe(LeadSource::Website)
        ->and($lead->concern)->toBe('Check engine light is on.')
        ->and($lead->contact_phone)->toBe('7195550142');
});

test('imported catalog keeps the foundry common-problem url and homepage headline', function (): void {
    $document = PublicWebsiteCatalog::document();
    $slugs = array_column($document['common_problems'], 'slug');

    expect($document['headline'])->toBe('Accurate Diagnostics. Honest Repairs.')
        ->and($document['source'])->toBe('foundry-public-catalog-and-php-defaults')
        ->and($slugs)->toContain('check-engine-light');
});

test('featured media marketing gallery is not registered in the app bundle', function (): void {
    $appJs = file_get_contents(resource_path('js/app.js'));

    expect($appJs)->not->toContain('ark-featured-media-gallery')
        ->and($appJs)->not->toContain('arkFeaturedMediaGallery')
        ->and(file_exists(resource_path('js/ark-featured-media-gallery.js')))->toBeFalse();
});
