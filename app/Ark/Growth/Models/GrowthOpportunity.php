<?php

namespace App\Ark\Growth\Models;

use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Growth\Opportunities\GrowthOpportunityAction;
use App\Ark\Growth\Opportunities\GrowthOpportunityEffort;
use App\Ark\Growth\Opportunities\GrowthOpportunityStatus;
use Database\Factories\GrowthOpportunityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrowthOpportunity extends Model
{
    /** @use HasFactory<GrowthOpportunityFactory> */
    use HasFactory;

    protected static function newFactory(): GrowthOpportunityFactory
    {
        return GrowthOpportunityFactory::new();
    }

    protected $fillable = [
        'key',
        'action_type',
        'title',
        'impact_summary',
        'effort',
        'status',
        'priority_score',
        'estimated_lift',
        'evidence',
        'acceptance_criteria',
        'content_draft',
        'growth_content_id',
        'search_query',
        'landing_path',
        'accepted_at',
        'published_at',
        'measuring_since',
        'validated_at',
        'measurement',
    ];

    protected function casts(): array
    {
        return [
            'action_type' => GrowthOpportunityAction::class,
            'effort' => GrowthOpportunityEffort::class,
            'status' => GrowthOpportunityStatus::class,
            'priority_score' => 'integer',
            'estimated_lift' => 'array',
            'evidence' => 'array',
            'acceptance_criteria' => 'array',
            'content_draft' => 'array',
            'measurement' => 'array',
            'accepted_at' => 'datetime',
            'published_at' => 'datetime',
            'measuring_since' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(GrowthContent::class, 'growth_content_id');
    }

    public function transitionTo(GrowthOpportunityStatus $status): void
    {
        if (! in_array($status, $this->status->allowedTransitions(), true)) {
            return;
        }

        $this->status = $status;

        if ($status === GrowthOpportunityStatus::Accepted && $this->accepted_at === null) {
            $this->accepted_at = now();
        }

        if ($status === GrowthOpportunityStatus::Published && $this->published_at === null) {
            $this->published_at = now();
        }

        if ($status === GrowthOpportunityStatus::Measuring && $this->measuring_since === null) {
            $this->measuring_since = now();
        }

        if ($status === GrowthOpportunityStatus::Validated && $this->validated_at === null) {
            $this->validated_at = now();
        }

        $this->save();
    }
}
