<?php

use App\Ark\Growth\Http\Controllers\GrowthIntegrationsController;
use App\Ark\Growth\Http\Controllers\GrowthMaintenanceRebuildController;
use App\Ark\Growth\Http\Controllers\GrowthIntegrationsDiscoverLocationsController;
use App\Ark\Growth\Http\Controllers\UpdateGrowthIntegrationsController;
use App\Ark\Growth\Http\Controllers\GrowthOpportunityBuildController;
use App\Ark\Growth\Http\Controllers\GrowthOpportunityContentController;
use App\Ark\Growth\Http\Controllers\GrowthOpportunityIndexController;
use App\Ark\Growth\Http\Controllers\GrowthOpportunityPreviewController;
use App\Ark\Growth\Http\Controllers\GrowthOpportunityStartController;
use App\Ark\Growth\Http\Controllers\GrowthOpportunityStatusController;
use App\Ark\Growth\Http\Controllers\JourneyExplorerController;
use App\Ark\Growth\Http\Controllers\GrowthContentIndexController;
use App\Ark\Growth\Http\Controllers\GrowthDashboardController;
use App\Ark\Growth\Http\Controllers\GrowthEventStoreController;
use App\Ark\Growth\Http\Controllers\GrowthRedirectIndexController;
use App\Ark\Growth\Http\Controllers\GrowthRedirectStoreController;
use App\Ark\Growth\Http\Controllers\GrowthSeoAuditController;
use App\Ark\Growth\Http\Controllers\GrowthEntityHealthController;
use App\Ark\Growth\Http\Controllers\MarkAudienceSurfaceVerifiedController;
use App\Ark\Growth\Http\Controllers\GrowthSessionIndexController;
use App\Ark\Growth\Http\Controllers\GrowthSessionShowController;
use App\Ark\Growth\Http\Controllers\GrowthSitemapIndexController;
use App\Ark\Growth\Http\Controllers\GrowthSitemapSectionController;
use App\Ark\Growth\Http\Controllers\RevenueExplorerController;
use App\Ark\Growth\Http\Middleware\ApplyGrowthRedirects;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Support\Facades\Route;

Route::prefix('app/growth')->name('growth.')->middleware(['auth', 'verified'])->group(function (): void {
    Route::middleware('permission:'.ArkCapability::GrowthAccess->value)->group(function (): void {
        Route::get('/', static fn () => redirect()->route('growth.opportunities.index'));
        Route::get('/integrations', GrowthIntegrationsController::class)->name('integrations.index');
        Route::patch('/integrations', UpdateGrowthIntegrationsController::class)->name('integrations.update');
        Route::post('/integrations/discover-locations', GrowthIntegrationsDiscoverLocationsController::class)->name('integrations.discover-locations');
        Route::get('/opportunities', GrowthOpportunityIndexController::class)->name('opportunities.index');
        Route::post('/maintenance/rebuild', GrowthMaintenanceRebuildController::class)->name('maintenance.rebuild');
        Route::get('/opportunities/{opportunity}/build', GrowthOpportunityBuildController::class)->name('opportunities.build');
        Route::get('/opportunities/{opportunity}/preview', GrowthOpportunityPreviewController::class)->name('opportunities.preview');
        Route::post('/opportunities/{opportunity}/start', GrowthOpportunityStartController::class)->name('opportunities.start');
        Route::patch('/opportunities/{opportunity}/content', GrowthOpportunityContentController::class)->name('opportunities.content');
        Route::patch('/opportunities/{opportunity}', GrowthOpportunityStatusController::class)->name('opportunities.status');
        Route::get('/dashboard', GrowthDashboardController::class)->name('dashboard');
        Route::get('/revenue-explorer', RevenueExplorerController::class)->name('revenue-explorer');
        Route::get('/journey-explorer', JourneyExplorerController::class)->name('journey-explorer');
        Route::get('/content', GrowthContentIndexController::class)->name('content.index');
        Route::get('/audit', GrowthSeoAuditController::class)->name('audit');
        Route::get('/entity-health', GrowthEntityHealthController::class)->name('entity-health');
        Route::patch('/entity-health/verify/{surface}', MarkAudienceSurfaceVerifiedController::class)->name('entity-health.verify');
        Route::get('/redirects', GrowthRedirectIndexController::class)->name('redirects.index');
        Route::post('/redirects', GrowthRedirectStoreController::class)->name('redirects.store');
        Route::get('/sessions', GrowthSessionIndexController::class)->name('sessions.index');
        Route::get('/sessions/{session}', GrowthSessionShowController::class)->name('sessions.show');
    });
});

Route::middleware([ApplyGrowthRedirects::class])->group(function (): void {
    Route::post('/growth/events', GrowthEventStoreController::class)
        ->middleware('throttle:'.config('growth.event_collection.throttle_per_minute', 120).',1')
        ->name('growth.events.store');

    Route::get('/growth/sitemap.xml', GrowthSitemapIndexController::class)->name('growth.sitemap.index');
    Route::get('/growth/sitemaps/{section}.xml', GrowthSitemapSectionController::class)
        ->where('section', '[a-z_]+')
        ->name('growth.sitemap.section');
});
