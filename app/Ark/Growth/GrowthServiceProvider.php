<?php

namespace App\Ark\Growth;

use App\Ark\Growth\Console\BackfillBusinessProfileCommand;
use App\Ark\Growth\Console\NotifySearchEnginesCommand;
use App\Ark\Growth\Console\RunGrowthNightlyMaintenanceCommand;
use App\Ark\Growth\Console\SyncBusinessProfileCommand;
use App\Ark\Growth\Console\SyncPublicContentRegistryCommand;
use App\Ark\Growth\Console\SubmitCommonProblemsForIndexingCommand;
use App\Ark\Growth\Console\SyncSearchConsoleCommand;
use App\Ark\Growth\Contracts\Operations\LeadConvertedForGrowth;
use App\Ark\Growth\Contracts\Operations\PublicSurfaceActivityRecorded;
use App\Ark\Growth\Contracts\Operations\RepairOrderClosedForGrowth;
use App\Ark\Growth\Events\GrowthOpportunityContentUpdated;
use App\Ark\Growth\Events\GrowthOpportunityPublished;
use App\Ark\Growth\Http\Middleware\ApplyGrowthRedirects;
use App\Ark\Growth\Http\Middleware\RecordPublicGrowthPageView;
use App\Ark\Growth\Integrations\Bing\BingWebmasterAdapter;
use App\Ark\Growth\Integrations\Contracts\AnalyticsAdapter;
use App\Ark\Growth\Integrations\Contracts\BusinessProfileAdapter;
use App\Ark\Growth\Integrations\Contracts\SearchConsoleAdapter;
use App\Ark\Growth\Integrations\Contracts\WebmasterAdapter;
use App\Ark\Growth\Integrations\FixtureBusinessProfileAdapter;
use App\Ark\Growth\Integrations\FixtureSearchConsoleAdapter;
use App\Ark\Growth\Integrations\Google\GoogleAnalytics4Adapter;
use App\Ark\Growth\Integrations\Google\GoogleBusinessProfileAdapter;
use App\Ark\Growth\Integrations\Google\GoogleSearchConsoleAdapter;
use App\Ark\Growth\Intelligence\Contracts\ContentSuggestionService;
use App\Ark\Growth\Intelligence\Contracts\ConversionRecommendationService;
use App\Ark\Growth\Intelligence\Contracts\OpportunityDiscoveryService;
use App\Ark\Growth\Intelligence\Contracts\RevenueInsightsService;
use App\Ark\Growth\Intelligence\Contracts\SearchTrendDetectionService;
use App\Ark\Growth\Intelligence\NullContentSuggestionService;
use App\Ark\Growth\Intelligence\NullConversionRecommendationService;
use App\Ark\Growth\Intelligence\NullOpportunityDiscoveryService;
use App\Ark\Growth\Intelligence\NullRevenueInsightsService;
use App\Ark\Growth\Intelligence\NullSearchTrendDetectionService;
use App\Ark\Growth\Listeners\LinkGrowthSessionOnLeadConversion;
use App\Ark\Growth\Listeners\RecordPublicSurfaceGrowthActivity;
use App\Ark\Growth\Listeners\RecordRepairOrderRevenueAttribution;
use App\Ark\Growth\Listeners\RunGrowthMaintenanceOnOpportunityChange;
use App\Ark\Growth\Seo\SchemaRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class GrowthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('growth.php'), 'growth');

        $this->app->singleton(SchemaRegistry::class);

        $this->app->bind(SearchConsoleAdapter::class, function ($app): SearchConsoleAdapter {
            $google = $app->make(GoogleSearchConsoleAdapter::class);

            if ($google->isConfigured()) {
                return $google;
            }

            return $app->make(FixtureSearchConsoleAdapter::class);
        });
        $this->app->bind(AnalyticsAdapter::class, GoogleAnalytics4Adapter::class);
        $this->app->bind(BusinessProfileAdapter::class, function ($app): BusinessProfileAdapter {
            $google = $app->make(GoogleBusinessProfileAdapter::class);

            if ($google->isConfigured()) {
                return $google;
            }

            return $app->make(FixtureBusinessProfileAdapter::class);
        });
        $this->app->bind(WebmasterAdapter::class, BingWebmasterAdapter::class);

        $this->app->bind(OpportunityDiscoveryService::class, NullOpportunityDiscoveryService::class);
        $this->app->bind(ContentSuggestionService::class, NullContentSuggestionService::class);
        $this->app->bind(SearchTrendDetectionService::class, NullSearchTrendDetectionService::class);
        $this->app->bind(ConversionRecommendationService::class, NullConversionRecommendationService::class);
        $this->app->bind(RevenueInsightsService::class, NullRevenueInsightsService::class);
    }

    public function boot(): void
    {
        if (! config('growth.enabled', true)) {
            return;
        }

        $this->loadViewsFrom(resource_path('views/growth'), 'growth');

        Route::middleware('web')
            ->group(base_path('routes/growth.php'));

        $this->app['router']->aliasMiddleware('growth.redirects', ApplyGrowthRedirects::class);
        $this->app['router']->aliasMiddleware('growth.public.pageview', RecordPublicGrowthPageView::class);

        Event::listen(RepairOrderClosedForGrowth::class, RecordRepairOrderRevenueAttribution::class);
        Event::listen(PublicSurfaceActivityRecorded::class, RecordPublicSurfaceGrowthActivity::class);
        Event::listen(LeadConvertedForGrowth::class, LinkGrowthSessionOnLeadConversion::class);
        Event::listen(GrowthOpportunityPublished::class, [RunGrowthMaintenanceOnOpportunityChange::class, 'handlePublished']);
        Event::listen(GrowthOpportunityContentUpdated::class, [RunGrowthMaintenanceOnOpportunityChange::class, 'handleContentUpdated']);

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncPublicContentRegistryCommand::class,
                SyncSearchConsoleCommand::class,
                SyncBusinessProfileCommand::class,
                BackfillBusinessProfileCommand::class,
                RunGrowthNightlyMaintenanceCommand::class,
                NotifySearchEnginesCommand::class,
                SubmitCommonProblemsForIndexingCommand::class,
            ]);
        }
    }
}
