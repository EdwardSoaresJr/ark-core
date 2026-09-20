<?php

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

test('root redirects to staff login without marketing homepage', function (): void {
    $this->get('/')
        ->assertRedirect(route('login'));
});

test('growth website and public marketing lead routes are not registered', function (): void {
    expect(Route::has('growth.opportunities.index'))->toBeFalse()
        ->and(Route::has('website.manage'))->toBeFalse()
        ->and(Route::has('website.performance'))->toBeFalse()
        ->and(Route::has('public.home'))->toBeFalse()
        ->and(Route::has('public.book'))->toBeFalse()
        ->and(Route::has('public.leads.store'))->toBeFalse()
        ->and(Route::has('public.leads.thanks'))->toBeFalse();
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

test('featured media marketing gallery is not registered in the app bundle', function (): void {
    $appJs = file_get_contents(resource_path('js/app.js'));

    expect($appJs)->not->toContain('ark-featured-media-gallery')
        ->and($appJs)->not->toContain('arkFeaturedMediaGallery')
        ->and(file_exists(resource_path('js/ark-featured-media-gallery.js')))->toBeFalse();
});
