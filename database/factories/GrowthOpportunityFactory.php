<?php

namespace Database\Factories;

use App\Ark\Growth\Models\GrowthOpportunity;
use App\Ark\Growth\Opportunities\GrowthOpportunityAction;
use App\Ark\Growth\Opportunities\GrowthOpportunityEffort;
use App\Ark\Growth\Opportunities\GrowthOpportunityStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GrowthOpportunity> */
class GrowthOpportunityFactory extends Factory
{
    protected $model = GrowthOpportunity::class;

    public function definition(): array
    {
        $query = fake()->unique()->slug(2);

        return [
            'key' => 'create:'.$query,
            'action_type' => GrowthOpportunityAction::Create,
            'title' => 'Create page for '.$query,
            'impact_summary' => 'Test impact summary.',
            'effort' => GrowthOpportunityEffort::Small,
            'status' => GrowthOpportunityStatus::Discovered,
            'priority_score' => 50,
            'estimated_lift' => ['steps' => []],
            'evidence' => ['facts' => [], 'signals' => []],
            'search_query' => $query,
        ];
    }
}
