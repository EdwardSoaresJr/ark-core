<?php

namespace App\Providers;

use App\Ark\ShopMemory\Projections\ConcernSuggestionProjection;
use App\Ark\ShopMemory\Projections\LaborSuggestionProjection;
use App\Ark\ShopMemory\Projections\SuggestionPresentation;
use App\Ark\ShopMemory\Providers\CustomerIntakeProvider;
use App\Ark\ShopMemory\Providers\HistoricalConcernProvider;
use App\Ark\ShopMemory\Providers\HistoricalLaborProvider;
use App\Ark\ShopMemory\Providers\InspectionFindingProvider;
use App\Ark\ShopMemory\Providers\TechnicianObservationProvider;
use App\Ark\ShopMemory\Rewrite\AiRewriteAction;
use App\Ark\ShopMemory\ShopMemoryFeatures;
use App\Ark\ShopMemory\ShopMemoryProviderCatalog;
use App\Ark\ShopMemory\Suggestion\SuggestionDeduper;
use App\Ark\ShopMemory\Suggestion\SuggestionEngine;
use App\Ark\ShopMemory\Suggestion\SuggestionPipeline;
use App\Ark\ShopMemory\Suggestion\SuggestionProvider;
use App\Ark\ShopMemory\Suggestion\SuggestionProviderRegistry;
use App\Ark\ShopMemory\Suggestion\SuggestionRanker;
use App\Ark\ShopMemory\Suggestion\SuggestionTextNormalizer;
use Illuminate\Support\ServiceProvider;

/**
 * Shop Memory — Suggestion Engine + gated provider registration.
 */
final class ShopMemoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SuggestionProviderRegistry::class);
        $this->app->singleton(SuggestionTextNormalizer::class);
        $this->app->singleton(SuggestionDeduper::class);
        $this->app->singleton(SuggestionRanker::class);
        $this->app->singleton(SuggestionPipeline::class);
        $this->app->singleton(SuggestionPresentation::class);
        $this->app->singleton(SuggestionEngine::class, function ($app): SuggestionEngine {
            return new SuggestionEngine(
                $app->make(SuggestionProviderRegistry::class),
                $app->make(SuggestionPipeline::class),
                (bool) config('app.debug'),
            );
        });
        $this->app->singleton(ConcernSuggestionProjection::class);
        $this->app->singleton(LaborSuggestionProjection::class);
        $this->app->singleton(HistoricalLaborProvider::class);
        $this->app->singleton(HistoricalConcernProvider::class);
        $this->app->singleton(TechnicianObservationProvider::class);
        $this->app->singleton(InspectionFindingProvider::class);
        $this->app->singleton(CustomerIntakeProvider::class);
        $this->app->singleton(AiRewriteAction::class);
    }

    public function boot(): void
    {
        $registry = $this->app->make(SuggestionProviderRegistry::class);

        foreach ($this->engineProviders() as $key => $class) {
            if (! ShopMemoryFeatures::providerEnabled($key)) {
                continue;
            }

            /** @var SuggestionProvider $provider */
            $provider = $this->app->make($class);
            $registry->register($provider);
        }
    }

    /**
     * Suggestion providers only — AI Rewrite is a sibling action, not engine-registered.
     *
     * @return array<string, class-string<SuggestionProvider>>
     */
    private function engineProviders(): array
    {
        return [
            ShopMemoryProviderCatalog::HISTORICAL_LABOR => HistoricalLaborProvider::class,
            ShopMemoryProviderCatalog::HISTORICAL_CONCERN => HistoricalConcernProvider::class,
            ShopMemoryProviderCatalog::TECHNICIAN_OBSERVATION => TechnicianObservationProvider::class,
            ShopMemoryProviderCatalog::INSPECTION_FINDING => InspectionFindingProvider::class,
            ShopMemoryProviderCatalog::CUSTOMER_INTAKE => CustomerIntakeProvider::class,
        ];
    }
}
