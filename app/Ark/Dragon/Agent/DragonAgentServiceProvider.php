<?php

namespace App\Ark\Dragon\Agent;

use App\Ark\Dragon\Agent\Contracts\DragonModelProvider;
use App\Ark\Dragon\Agent\Providers\FakeDragonProvider;
use App\Ark\Dragon\Agent\Providers\NotConfiguredDragonProvider;
use App\Ark\Dragon\Agent\Tools\AdvisorTasksQueryTool;
use App\Ark\Dragon\Agent\Tools\AppointmentsQueryTool;
use App\Ark\Dragon\Agent\Tools\EstimatesAdvisorContextTool;
use App\Ark\Dragon\Agent\Tools\EstimatesGetTool;
use App\Ark\Dragon\Agent\Tools\HistorySearchTool;
use App\Ark\Dragon\Agent\Tools\InspectionsGetTool;
use App\Ark\Dragon\Agent\Tools\KnowledgeSearchTool;
use App\Ark\Dragon\Agent\Tools\MemoryProposeTool;
use App\Ark\Dragon\Agent\Tools\MemoryRecallTool;
use App\Ark\Dragon\Agent\Tools\RepairOrdersGetTool;
use App\Ark\Dragon\Agent\Tools\RepairOrdersSearchTool;
use App\Ark\Dragon\Agent\Tools\ShopCurrentSummaryTool;
use App\Ark\Dragon\Agent\Tools\ShopFinancialSnapshotTool;
use Illuminate\Support\ServiceProvider;

final class DragonAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DragonMemoryContext::class, fn (): DragonMemoryContext => DragonMemoryContext::empty());

        $this->app->singleton(DragonToolRegistry::class, function ($app): DragonToolRegistry {
            return new DragonToolRegistry([
                $app->make(ShopCurrentSummaryTool::class),
                $app->make(AdvisorTasksQueryTool::class),
                $app->make(AppointmentsQueryTool::class),
                $app->make(RepairOrdersSearchTool::class),
                $app->make(RepairOrdersGetTool::class),
                $app->make(EstimatesGetTool::class),
                $app->make(InspectionsGetTool::class),
                $app->make(MemoryRecallTool::class),
                $app->make(MemoryProposeTool::class),
                $app->make(KnowledgeSearchTool::class),
                $app->make(HistorySearchTool::class),
                $app->make(ShopFinancialSnapshotTool::class),
                $app->make(EstimatesAdvisorContextTool::class),
            ]);
        });

        $this->app->singleton(FakeDragonProvider::class);

        $this->app->singleton(NotConfiguredDragonProvider::class);

        $this->app->bind(DragonModelProvider::class, function ($app): DragonModelProvider {
            $provider = (string) config('dragon.provider', 'none');

            return match ($provider) {
                'fake' => $app->make(FakeDragonProvider::class),
                default => $app->make(NotConfiguredDragonProvider::class),
            };
        });
    }
}
