<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Settings\ShopSettings;

final class ConcernRecommendationDefaults
{
    /**
     * @return array{recommendation_intent: ?string, recommendation_priority: ?string, information_only: bool}
     */
    public static function forIntake(RepairOrder $repairOrder): array
    {
        return self::pack(
            RecommendationIntent::defaultSemanticForRepairOrder($repairOrder),
            ShopSettings::current(),
            informationOnlyFromKind: false,
        );
    }

    /**
     * @return array{recommendation_intent: ?string, recommendation_priority: ?string, information_only: bool}
     */
    public static function forEntryKind(
        ScopeEntryKind $entryKind,
        ?string $explicitSemantic = null,
    ): array {
        if (filled($explicitSemantic) && RecommendationPriority::isHistoricalInformationOnly($explicitSemantic)) {
            return [
                'recommendation_intent' => null,
                'recommendation_priority' => null,
                'information_only' => true,
            ];
        }

        $explicitPriority = filled($explicitSemantic)
            ? RecommendationPriority::fromHistoricalIntent($explicitSemantic)
            : null;

        if ($explicitPriority instanceof RecommendationPriority && RecommendationIntent::trySemantic($explicitSemantic) === null) {
            return [
                'recommendation_intent' => null,
                'recommendation_priority' => $explicitPriority->value,
                'information_only' => false,
            ];
        }

        $semantic = filled($explicitSemantic)
            ? RecommendationIntent::trySemantic($explicitSemantic)
            : $entryKind->defaultRecommendationIntent();

        return self::pack(
            $semantic,
            ShopSettings::current(),
            informationOnlyFromKind: $explicitSemantic === null && $entryKind->defaultsToInformationOnly(),
        );
    }

    /**
     * @return array{recommendation_intent: ?string, recommendation_priority: ?string, information_only: bool}
     */
    private static function pack(
        ?RecommendationIntent $semantic,
        ShopSettings $settings,
        bool $informationOnlyFromKind,
    ): array {
        if ($informationOnlyFromKind || $settings->defaultsToInformationOnly()) {
            return [
                'recommendation_intent' => $semantic?->value,
                'recommendation_priority' => null,
                'information_only' => true,
            ];
        }

        return [
            'recommendation_intent' => $semantic?->value,
            'recommendation_priority' => $settings->defaultRecommendationPriority()?->value,
            'information_only' => false,
        ];
    }
}
