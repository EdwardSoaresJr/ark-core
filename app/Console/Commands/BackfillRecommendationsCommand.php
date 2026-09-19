<?php

namespace App\Console\Commands;

use App\Ark\Operations\Recommendations\CreateRecommendationAction;
use App\Ark\Operations\Recommendations\Recommendation;
use App\Ark\Operations\Recommendations\RecommendationSourceKind;
use App\Ark\Operations\Recommendations\RecommendationUrgency;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use Illuminate\Console\Command;

final class BackfillRecommendationsCommand extends Command
{

    protected $signature = 'ark:recommendations:backfill {--dry-run : Count without writing}';

    protected $description = 'Create open recommendations from deferred and declined estimate scopes without fabricating presentation history.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $skipped = 0;

        RepairOrderConcern::query()
            ->with(['repairOrder.customer', 'repairOrder.vehicle'])
            ->whereIn('disposition', [
                RepairOrderConcernDisposition::Deferred->value,
                RepairOrderConcernDisposition::Declined->value,
            ])
            ->orderBy('id')
            ->chunkById(100, function ($concerns) use ($dryRun, &$created, &$skipped): void {
                foreach ($concerns as $concern) {
                    $repairOrder = $concern->repairOrder;

                    if ($repairOrder === null || $repairOrder->customer === null || $repairOrder->vehicle === null) {
                        $skipped++;

                        continue;
                    }

                    $exists = Recommendation::query()
                        ->where('originating_repair_order_concern_id', $concern->id)
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $created++;

                        continue;
                    }

                    app(CreateRecommendationAction::class)->handle([
                        'customer' => $repairOrder->customer,
                        'vehicle' => $repairOrder->vehicle,
                        'title' => $concern->summary,
                        'customer_description' => $concern->recommendation ?: $concern->verified_findings,
                        'advisor_note' => null,
                        'originating_repair_order' => $repairOrder,
                        'originating_concern' => $concern,
                        'discovered_at' => $concern->created_at,
                        'discovered_mileage' => $repairOrder->resolvedMileageIn(),
                        'urgency' => RecommendationUrgency::fromIntent($concern->recommendationIntent()),
                        'safety_related' => $concern->recommendationIntent() === RecommendationIntent::ImmediateAttention,
                        'source_kind' => RecommendationSourceKind::Estimate,
                    ]);

                    $created++;
                }
            });

        $this->info(($dryRun ? 'Would create ' : 'Created ').$created.' recommendation'.($created === 1 ? '' : 's').' ('.$skipped.' skipped). No presentation or decline events were invented.');

        return self::SUCCESS;
    }
}
