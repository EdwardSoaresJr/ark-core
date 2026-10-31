<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Support\Collection;

/**
 * The only visual contract for recommendation priority.
 * Colors, order, CSS classes, and customer labels come from here.
 */
final class RecommendationPresentation
{
    public const INFORMATION_ONLY = 'information_only';

    public const UNSET = 'unset';

    public const INFORMATION_ONLY_ACCENT = '#0369a1';

    public const INFORMATION_ONLY_TINT = '#f0f9ff';

    public const UNSET_ACCENT = '#64748b';

    public const UNSET_TINT = '#f8fafc';

    private function __construct(
        public readonly ?RecommendationPriority $priority,
        public readonly bool $informationOnly,
        private readonly ?string $storedStaffLabel = null,
        private readonly ?string $storedCustomerLabel = null,
    ) {}

    public static function priority(RecommendationPriority $priority): self
    {
        return new self($priority, false);
    }

    public static function informationOnly(): self
    {
        return new self(null, true);
    }

    public static function unset(): self
    {
        return new self(null, false);
    }

    public static function forConcern(RepairOrderConcern $concern): self
    {
        if ($concern->isInformationOnly()) {
            return self::informationOnly();
        }

        $priority = $concern->recommendationPriority();

        return $priority instanceof RecommendationPriority
            ? self::priority($priority)
            : self::unset();
    }

    /**
     * New snapshots carry recommendation_priority. Older issued snapshots keep
     * the label already stored on the document.
     *
     * @param  array<string, mixed>  $concern
     */
    public static function forSnapshot(array $concern): self
    {
        if (array_key_exists('recommendation_priority', $concern) || array_key_exists('information_only', $concern)) {
            if ((bool) ($concern['information_only'] ?? false)) {
                return self::informationOnly();
            }

            $priority = RecommendationPriority::tryFromStored(
                isset($concern['recommendation_priority']) ? (string) $concern['recommendation_priority'] : null,
            );

            return $priority instanceof RecommendationPriority
                ? self::priority($priority)
                : self::unset();
        }

        $storedIntent = isset($concern['recommendation_intent']) ? (string) $concern['recommendation_intent'] : '';
        $storedStaff = self::filledString($concern['recommendation_intent_label'] ?? null);
        $storedCustomer = self::filledString($concern['recommendation_intent_customer_label'] ?? null) ?? $storedStaff;

        if (RecommendationPriority::isHistoricalInformationOnly($storedIntent)) {
            return new self(null, true, $storedStaff, $storedCustomer);
        }

        $priority = RecommendationPriority::fromHistoricalIntent($storedIntent);

        if ($priority instanceof RecommendationPriority) {
            return new self($priority, false, $storedStaff, $storedCustomer);
        }

        return new self(null, false, $storedStaff, $storedCustomer);
    }

    /**
     * @return list<self>
     */
    public static function catalog(): array
    {
        $entries = [];

        foreach (RecommendationPriority::cases() as $priority) {
            $entries[] = self::priority($priority);
        }

        $entries[] = self::informationOnly();
        $entries[] = self::unset();

        return $entries;
    }

    public function key(): string
    {
        if ($this->informationOnly) {
            return self::INFORMATION_ONLY;
        }

        return $this->priority?->value ?? self::UNSET;
    }

    public function sortKey(): int
    {
        if ($this->informationOnly) {
            return 3;
        }

        return $this->priority?->sortKey() ?? 4;
    }

    public function staffLabel(): ?string
    {
        if ($this->storedStaffLabel !== null) {
            return $this->storedStaffLabel;
        }

        if ($this->informationOnly) {
            return 'Information Only';
        }

        return $this->priority?->staffLabel();
    }

    public function customerLabel(): ?string
    {
        if ($this->storedCustomerLabel !== null) {
            return $this->storedCustomerLabel;
        }

        if ($this->informationOnly) {
            return 'Information Only';
        }

        return $this->priority?->customerLabel();
    }

    public function helpText(): string
    {
        if ($this->informationOnly) {
            return 'Document context only. No recommended action.';
        }

        if ($this->priority instanceof RecommendationPriority) {
            return $this->priority->helpText();
        }

        return 'Priority has not been set.';
    }

    public function accentColor(): string
    {
        if ($this->informationOnly) {
            return self::INFORMATION_ONLY_ACCENT;
        }

        return $this->priority?->accentColor() ?? self::UNSET_ACCENT;
    }

    public function tintBackground(): string
    {
        if ($this->informationOnly) {
            return self::INFORMATION_ONLY_TINT;
        }

        return $this->priority?->tintBackground() ?? self::UNSET_TINT;
    }

    public function darkTintColor(): string
    {
        return $this->accentColor();
    }

    public function deferredFollowUpAction(): string
    {
        if ($this->informationOnly) {
            return 'Review informational note on next visit';
        }

        return $this->priority?->deferredFollowUpAction()
            ?? 'Deferred work retained for next-visit continuity.';
    }

    public function continuityReminder(): string
    {
        if ($this->informationOnly) {
            return 'Informational note retained for next-visit continuity.';
        }

        return $this->priority?->continuityReminder()
            ?? 'Deferred work retained for next-visit continuity.';
    }

    public function pdfScopeClass(): string
    {
        return 'concern--priority-'.$this->key();
    }

    public function worksheetScopeClass(): string
    {
        return 'ops-worksheet-concern--priority-'.$this->key();
    }

    public function reviewScopeClass(): string
    {
        return 'ops-review-concern--priority-'.$this->key();
    }

    /** Tabler color name for the priority palette - sync with the ops-worksheet-concern--priority-* accents. */
    public function tablerColor(): string
    {
        return match ($this->key()) {
            RecommendationPriority::MustDo->value => 'red',
            RecommendationPriority::ShouldDo->value => 'indigo',
            RecommendationPriority::CouldDo->value => 'orange',
            self::INFORMATION_ONLY => 'azure',
            default => 'secondary',
        };
    }

    public function labelClass(): string
    {
        return 'ops-priority-label--'.$this->key();
    }

    public function badgeClass(): string
    {
        return 'concern-priority-badge--'.$this->key();
    }

    public function groupHeadingClass(): string
    {
        return 'ops-priority-group-heading--'.$this->key();
    }

    /**
     * @return list<array{label: string, detail: string}>
     */
    public static function advisorHelpOverviewItems(): array
    {
        $items = [];

        foreach (RecommendationPriority::cases() as $priority) {
            $items[] = [
                'label' => $priority->staffLabel(),
                'detail' => $priority->helpText(),
            ];
        }

        $items[] = [
            'label' => 'Information Only',
            'detail' => self::informationOnly()->helpText(),
        ];

        foreach (RecommendationIntent::cases() as $semantic) {
            $items[] = [
                'label' => $semantic->staffLabel(),
                'detail' => $semantic->helpText().' Work type, not priority.',
            ];
        }

        return [
            ...$items,
            ...RecommendationIntent::advisorMisuseOverviewItems(),
        ];
    }

    /**
     * Strongest deferred follow-up is priority only. Work type does not rank.
     *
     * @param  iterable<RepairOrderConcern>  $concerns
     */
    public static function strongestDeferred(iterable $concerns): ?self
    {
        $strongest = null;

        foreach ($concerns as $concern) {
            if (! $concern instanceof RepairOrderConcern) {
                continue;
            }

            $presentation = $concern->presentation();

            if ($presentation->priority === null && ! $presentation->informationOnly) {
                continue;
            }

            if ($strongest === null || $presentation->sortKey() < $strongest->sortKey()) {
                $strongest = $presentation;
            }
        }

        return $strongest;
    }

    /**
     * @param  iterable<RepairOrderConcern>  $concerns
     * @return Collection<int, RepairOrderConcern>
     */
    public static function sortedModels(iterable $concerns): Collection
    {
        return collect($concerns)
            ->sort(function (RepairOrderConcern $left, RepairOrderConcern $right): int {
                $byPriority = $left->presentation()->sortKey() <=> $right->presentation()->sortKey();

                if ($byPriority !== 0) {
                    return $byPriority;
                }

                $byPosition = ((int) $left->position) <=> ((int) $right->position);

                if ($byPosition !== 0) {
                    return $byPosition;
                }

                return ((int) $left->id) <=> ((int) $right->id);
            })
            ->values();
    }

    /**
     * @param  list<array<string, mixed>>|iterable<mixed>  $concerns
     * @return Collection<int, array<string, mixed>>
     */
    public static function sortedSnapshotConcerns(iterable $concerns): Collection
    {
        return collect($concerns)
            ->filter(fn ($concern): bool => is_array($concern))
            ->sort(function (array $left, array $right): int {
                $byPriority = self::forSnapshot($left)->sortKey() <=> self::forSnapshot($right)->sortKey();

                if ($byPriority !== 0) {
                    return $byPriority;
                }

                return ((int) ($left['position'] ?? 0)) <=> ((int) ($right['position'] ?? 0));
            })
            ->values();
    }

    /**
     * @param  iterable<RepairOrderConcern>  $concerns
     * @return list<array{type: 'concern', concern: RepairOrderConcern}>
     */
    public static function displayEntriesForModels(iterable $concerns): array
    {
        return self::sortedModels($concerns)
            ->map(fn (RepairOrderConcern $concern): array => [
                'type' => 'concern',
                'concern' => $concern,
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $concerns
     * @return list<array{type: 'concern', concern: array<string, mixed>}>
     */
    public static function displayEntriesForSnapshot(array $concerns): array
    {
        return self::sortedSnapshotConcerns($concerns)
            ->map(fn (array $concern): array => [
                'type' => 'concern',
                'concern' => $concern,
            ])
            ->all();
    }

    private static function filledString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
