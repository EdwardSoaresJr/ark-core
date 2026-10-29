<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores Diagnostic, Maintenance, and Repair Verification on recommendation_intent.
 * Legacy urgency values are written onto priority or information-only instead.
 *
 * @implements CastsAttributes<?RecommendationIntent, mixed>
 */
class RecommendationIntentCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?RecommendationIntent
    {
        return RecommendationIntent::trySemantic(is_string($value) ? $value : null);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value instanceof RecommendationIntent) {
            return [$key => $value->value];
        }

        $raw = is_string($value) ? trim($value) : null;

        if ($raw === null || $raw === '') {
            return [$key => null];
        }

        $semantic = RecommendationIntent::trySemantic($raw);

        if ($semantic instanceof RecommendationIntent) {
            return [$key => $semantic->value];
        }

        $priority = RecommendationPriority::fromHistoricalIntent($raw);

        if ($priority instanceof RecommendationPriority) {
            return [
                $key => null,
                'recommendation_priority' => $priority->value,
                'information_only' => false,
            ];
        }

        if (RecommendationPriority::isHistoricalInformationOnly($raw)) {
            return [
                $key => null,
                'recommendation_priority' => null,
                'information_only' => true,
            ];
        }

        return [$key => null];
    }
}
