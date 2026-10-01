<?php

namespace App\Ark\Operations\WorkTemplates;

use App\Ark\Operations\RepairOrders\RecommendationIntent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shop canned-work definition. Authors Repair Actions + lines on apply, then owns nothing.
 */
class WorkTemplate extends Model
{
    protected $fillable = [
        'title',
        'description',
        'internal_note',
        'recommendation_intent',
        'position',
        'retired_at',
    ];

    protected $attributes = [
        'recommendation_intent' => 'maintenance',
    ];

    protected function casts(): array
    {
        return [
            'retired_at' => 'datetime',
            'position' => 'integer',
            'recommendation_intent' => RecommendationIntent::class,
        ];
    }

    public function recommendationIntent(): RecommendationIntent
    {
        $intent = $this->recommendation_intent;

        return $intent instanceof RecommendationIntent
            ? $intent
            : RecommendationIntent::fromStored(is_string($intent) ? $intent : null);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(WorkTemplateLine::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    public function isRetired(): bool
    {
        return $this->retired_at !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('retired_at');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('title', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    public function retire(): void
    {
        if ($this->retired_at !== null) {
            return;
        }

        $this->forceFill(['retired_at' => now()])->save();
    }

    public function restoreFromRetirement(): void
    {
        $this->forceFill(['retired_at' => null])->save();
    }

    /**
     * @return array{title: string, description: ?string, lines: list<array<string, mixed>>, internal_note: ?string}
     */
    public function previewPayload(): array
    {
        $this->loadMissing('lines');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'internal_note' => $this->internal_note,
            'recommendation_intent' => $this->recommendationIntent()->value,
            'recommendation_intent_label' => $this->recommendationIntent()->staffLabel(),
            'lines' => $this->lines->map(fn (WorkTemplateLine $line): array => [
                'type' => $line->type->value,
                'type_label' => $line->type->documentLabel(),
                'description' => $line->description,
                'quantity' => (string) $line->quantity,
                'hours' => $line->type->isLabor() ? (string) $line->quantity : null,
            ])->values()->all(),
        ];
    }
}
