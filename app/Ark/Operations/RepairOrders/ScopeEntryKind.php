<?php

namespace App\Ark\Operations\RepairOrders;

enum ScopeEntryKind: string
{
    case CustomerConcern = 'customer_concern';
    case CustomerRequested = 'customer_requested';
    case Diagnostic = 'diagnostic';
    case WarrantyRecheck = 'warranty_recheck';
    case CourtesyInspection = 'courtesy_inspection';

    public function staffLabel(): string
    {
        return match ($this) {
            self::CustomerConcern => 'Customer Concern',
            self::CustomerRequested => 'Customer Requested',
            self::Diagnostic => 'Diagnostic',
            self::WarrantyRecheck => 'Warranty / Recheck',
            self::CourtesyInspection => 'Courtesy Inspection',
        };
    }

    public function summaryFieldLabel(): string
    {
        return match ($this) {
            self::CustomerConcern => 'Customer concern',
            self::CustomerRequested => 'Customer requested',
            self::Diagnostic => 'Diagnostic request',
            self::WarrantyRecheck => 'Warranty / recheck',
            self::CourtesyInspection => 'Courtesy inspection',
        };
    }

    public function summaryPlaceholder(): string
    {
        return match ($this) {
            self::CustomerConcern => 'e.g. Brake noise, overheating, check engine light',
            self::CustomerRequested => 'e.g. Front brake service, oil change, battery replacement',
            self::Diagnostic => 'e.g. No start, electrical diagnosis, parasitic draw',
            self::WarrantyRecheck => 'e.g. Recheck coolant leak, brake pulsation after repair',
            self::CourtesyInspection => 'e.g. Courtesy inspection, multi-point check',
        };
    }

    /**
     * @return list<string>
     */
    public function summaryExamples(): array
    {
        return match ($this) {
            self::CustomerConcern => ['Brake noise', 'Overheating', 'Check engine light'],
            self::CustomerRequested => ['Oil change', 'Front brake service', 'Battery replacement', 'Transmission service'],
            self::Diagnostic => ['No start', 'Electrical diagnosis', 'Parasitic draw'],
            self::WarrantyRecheck => ['Recheck coolant leak', 'Brake pulsation after repair'],
            self::CourtesyInspection => ['Courtesy inspection', 'Multi-point check'],
        };
    }

    public function vocabularyGroupLabel(): string
    {
        return match ($this) {
            self::CustomerConcern => 'Customer concern',
            self::CustomerRequested => 'Customer requested',
            self::Diagnostic => 'Diagnostic',
            self::WarrantyRecheck => 'Warranty / recheck',
            self::CourtesyInspection => 'Courtesy inspection',
        };
    }

    public function defaultRecommendationIntent(): RecommendationIntent
    {
        return match ($this) {
            self::CustomerConcern => RecommendationIntent::Diagnostic,
            self::CustomerRequested => RecommendationIntent::Maintenance,
            self::Diagnostic => RecommendationIntent::Diagnostic,
            self::WarrantyRecheck => RecommendationIntent::RepairVerification,
            self::CourtesyInspection => RecommendationIntent::InformationOnly,
        };
    }

    public static function inferFromSummary(string $summary): self
    {
        $normalized = mb_strtolower(trim($summary));

        if ($normalized === '') {
            return self::CustomerRequested;
        }

        if (preg_match('/\b(recheck|warranty|comeback|come back|after repair|still leak|still doing|return visit)\b/u', $normalized) === 1) {
            return self::WarrantyRecheck;
        }

        if (preg_match('/\b(courtesy inspection|multi.?point|mpi|vehicle inspection)\b/u', $normalized) === 1) {
            return self::CourtesyInspection;
        }

        if (preg_match('/\b(no start|won\'?t start|parasitic|electrical diagn|diagnos|intermittent|dead battery test)\b/u', $normalized) === 1) {
            return self::Diagnostic;
        }

        if (preg_match('/\b(sounds like|feels like|seems like|acts like)\b/u', $normalized) === 1) {
            return self::CustomerConcern;
        }

        if (preg_match('/\b(noise|leak|overheat\w*|vibrat\w*|shak\w*|smell|rough|stall|misfire|grind\w*|squeal\w*|squeak\w*|pulsat\w*|check engine|cel|light on|runs hot|shudder)\b/u', $normalized) === 1) {
            return self::CustomerConcern;
        }

        return self::CustomerRequested;
    }

    public static function fromStored(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::CustomerConcern;
    }

    /**
     * @return list<self>
     */
    public static function intakeChoices(): array
    {
        return self::cases();
    }
}
