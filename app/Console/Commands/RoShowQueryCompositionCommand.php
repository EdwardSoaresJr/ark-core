<?php

namespace App\Console\Commands;

use App\Ark\Operations\Diagnostics\QueryCompositionCollector;
use App\Ark\Operations\Diagnostics\QueryCompositionFixtures;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoShowQueryCompositionCommand extends Command
{
    protected $signature = 'ark:query-composition:ro-show
        {repairOrder? : Repair order id}
        {--review : Profile estimate review instead of show}
        {--markdown : Print markdown report}';

    protected $description = 'Profile SQL query composition for RO show or estimate review GET requests.';

    public function handle(QueryCompositionCollector $collector): int
    {
        $this->seedOperationalPrerequisites();

        $repairOrder = $this->resolveRepairOrder();
        $review = (bool) $this->option('review');
        $routeName = $review
            ? 'operations.repair-orders.show'
            : 'operations.repair-orders.show';
        $url = route($routeName, $repairOrder);

        $advisor = User::query()->role(ArkRole::Advisor->value)->first()
            ?? User::factory()->create()->assignRole(ArkRole::Advisor->value);

        Auth::login($advisor);

        $report = $collector->measure(function () use ($url): void {
            $request = Request::create($url, 'GET');
            app()->handle($request);
        });

        $surface = $review ? 'Estimate Review' : 'RO Show';

        if ($this->option('markdown')) {
            $this->line($report->toMarkdown($surface, $url));

            return self::SUCCESS;
        }

        $this->components->info("Query composition for {$surface} · RO #{$repairOrder->repair_order_id}");
        $this->line('Total queries: '.$report->totalQueries);
        $this->line('repair_order_lines UPDATEs: '.$report->updateQueries);
        $this->newLine();
        $this->table(['Source', 'Queries', 'Share'], $report->rows());

        foreach (QueryCompositionReport::breakdownCategories() as $category) {
            if (($report->counts[$category] ?? 0) === 0) {
                continue;
            }

            $this->newLine();
            $this->components->info("{$category} breakdown");
            $this->table(['Subcategory', 'Queries', 'Share'], $report->subcategoryRows($category));
        }

        if ($report->getMutations !== []) {
            $this->newLine();
            $this->components->info('GET mutations (Read/Write Audit)');
            $this->table(['Subsystem', 'Mutations', 'Share'], $report->getMutationRows());
        }

        return self::SUCCESS;
    }

    private function seedOperationalPrerequisites(): void
    {
        (new ArkAuthorizationSeeder)->run();
        (new RepairOrderStatusCatalogSeeder)->run();
    }

    private function resolveRepairOrder(): RepairOrder
    {
        $id = $this->argument('repairOrder');

        if ($id !== null) {
            return RepairOrder::query()->findOrFail($id);
        }

        return QueryCompositionFixtures::representativeRepairOrder();
    }
}
