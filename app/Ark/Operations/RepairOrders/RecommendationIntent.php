<?php

namespace App\Ark\Operations\RepairOrders;

enum RecommendationIntent: string
{
    case ImmediateAttention = 'immediate_attention';
    case PlanSoon = 'plan_soon';
    case Maintenance = 'maintenance';
    case Diagnostic = 'diagnostic';
    case RepairVerification = 'repair_verification';
    case InformationOnly = 'information_only';

    public function staffLabel(): string
    {
        return match ($this) {
            self::ImmediateAttention => 'Immediate Attention',
            self::PlanSoon => 'Plan Soon',
            self::Maintenance => 'Maintenance',
            self::Diagnostic => 'Diagnostic',
            self::RepairVerification => 'Repair Verification',
            self::InformationOnly => 'Information Only',
        };
    }

    public function customerLabel(): string
    {
        return $this->staffLabel();
    }

    public function helpText(): string
    {
        return match ($this) {
            self::ImmediateAttention => 'Customer should act before return or further driving — e.g. brake pads at 1mm, coolant leak, loose steering.',
            self::PlanSoon => 'Schedule in the near term — e.g. battery at 28%, tires approaching replacement, spark plugs due.',
            self::Maintenance => 'Planned upkeep — e.g. fluid service, timing belt interval, manufacturer maintenance item.',
            self::Diagnostic => 'More testing needed before repair is recommended — e.g. check engine light with unknown root cause.',
            self::RepairVerification => 'Confirm completed work — e.g. recheck after repair, drive cycle, post-repair monitor.',
            self::InformationOnly => 'Document context only — e.g. Banks intake installed, customer-supplied lift kit, cosmetic observation.',
        };
    }

    /**
     * Recommendation status describes customer action — not part classification.
     *
     * @return list<array{label: string, detail: string}>
     */
    public static function advisorMisuseOverviewItems(): array
    {
        return [
            [
                'label' => 'Not for part source',
                'detail' => 'Customer-supplied vs shop-supplied parts belong on the part line — not recommendation status.',
            ],
            [
                'label' => 'Not for OEM / aftermarket / performance',
                'detail' => 'Part type belongs on the part line. A brake job can be Immediate Attention regardless of pad brand.',
            ],
            [
                'label' => 'Not for warranty posture',
                'detail' => 'Scope billing and part warranty impact are separate authorities.',
            ],
        ];
    }

    /**
     * @return list<array{label: string, detail: string}>
     */
    public static function advisorHelpOverviewItems(): array
    {
        return [
            ...collect(self::cases())
                ->map(fn (self $intent): array => [
                    'label' => $intent->staffLabel(),
                    'detail' => $intent->helpText(),
                ])
                ->all(),
            ...self::advisorMisuseOverviewItems(),
        ];
    }

    /** Customer-facing PDF section heading for presentation-only intent grouping. */
    public function pdfGroupLabel(): string
    {
        return $this->staffLabel();
    }

    /** Category accent — wayfinding, not urgency scoring. Authoritative across PDF + worksheet. */
    public function accentColor(): string
    {
        return match ($this) {
            self::ImmediateAttention => '#9f1239',
            self::Diagnostic => '#0f766e',
            self::RepairVerification => '#7c3aed',
            self::Maintenance => '#b45309',
            self::PlanSoon => '#4338ca',
            self::InformationOnly => '#0369a1',
        };
    }

    public function tintBackground(): string
    {
        return match ($this) {
            self::ImmediateAttention => '#fff1f2',
            self::Diagnostic => '#f0fdfa',
            self::RepairVerification => '#f5f3ff',
            self::Maintenance => '#fffbeb',
            self::PlanSoon => '#eef2ff',
            self::InformationOnly => '#f0f9ff',
        };
    }

    public function pdfScopeClass(): string
    {
        return 'concern--intent-'.$this->value;
    }

    public function worksheetScopeClass(): string
    {
        return 'ops-worksheet-concern--intent-'.$this->value;
    }

    public function reviewScopeClass(): string
    {
        return 'ops-review-concern--intent-'.$this->value;
    }

    public function intentLabelClass(): string
    {
        return 'ops-intent-label--'.$this->value;
    }

    public function groupHeadingClass(): string
    {
        return 'ops-intent-group-heading--'.$this->value;
    }

    public function intentGroupClass(): string
    {
        return 'ops-intent-group--intent-'.$this->value;
    }

    /**
     * Presentation order for estimate PDF / review intent groups and related surfaces.
     * Diagnostic leads — diagnose before recommended work.
     *
     * @return array<string, int>
     */
    public static function pdfGroupOrder(): array
    {
        return [
            self::Diagnostic->value => 0,
            self::ImmediateAttention->value => 1,
            self::RepairVerification->value => 2,
            self::Maintenance->value => 3,
            self::PlanSoon->value => 4,
            self::InformationOnly->value => 5,
        ];
    }

    /** Sort key so worksheet / PDF / review keep priority order without group wrappers. */
    public function worksheetPinSortKey(): int
    {
        return self::pdfGroupOrder()[$this->value] ?? 99;
    }

    public function deferredFollowUpAction(): string
    {
        return match ($this) {
            self::ImmediateAttention => 'Revisit immediate attention item at next contact',
            self::PlanSoon => 'Schedule plan-soon work at next visit',
            self::Maintenance => 'Schedule maintenance at next service',
            self::Diagnostic => 'Complete diagnostics before recommending repair',
            self::RepairVerification => 'Confirm repair verification on next visit',
            self::InformationOnly => 'Review informational note on next visit',
        };
    }

    public function continuityReminder(): string
    {
        return match ($this) {
            self::ImmediateAttention => 'Immediate attention item should be revisited calmly.',
            self::Diagnostic => 'Diagnostics should be completed before repair is recommended.',
            default => 'Deferred work retained for next-visit continuity.',
        };
    }

    public static function tryFromStored(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::tryFrom($value) ?? self::fromLegacy($value);
    }

    public static function fromStored(?string $value): self
    {
        return self::tryFromStored($value) ?? self::Maintenance;
    }

    public static function fromLegacy(string $value): ?self
    {
        return match ($value) {
            'high', 'immediate_attention' => self::ImmediateAttention,
            'normal', 'maintenance' => self::Maintenance,
            'low', 'information_only' => self::InformationOnly,
            'plan_soon' => self::PlanSoon,
            'diagnostic' => self::Diagnostic,
            default => null,
        };
    }

    public static function defaultForRepairOrder(RepairOrder $repairOrder): self
    {
        if (RepairOrderWorkflowStatus::from($repairOrder->status)->is(RepairOrderStatus::Draft)) {
            return self::Diagnostic;
        }

        return self::fromStored(
            \App\Ark\Operations\Settings\ShopSettings::current()->default_recommendation_intent,
        );
    }

    /**
     * Strongest deferred follow-up intent wins when multiple concerns are retained.
     *
     * @param  iterable<RepairOrderConcern>  $concerns
     */
    public static function strongestDeferredFollowUp(iterable $concerns): ?self
    {
        $order = [
            self::ImmediateAttention,
            self::Diagnostic,
            self::RepairVerification,
            self::Maintenance,
            self::PlanSoon,
            self::InformationOnly,
        ];

        $present = [];

        foreach ($concerns as $concern) {
            $present[$concern->recommendationIntent()->value] = $concern->recommendationIntent();
        }

        foreach ($order as $intent) {
            if (isset($present[$intent->value])) {
                return $intent;
            }
        }

        return null;
    }

    /**
     * Flat concern list ordered by priority, then advisor position.
     * Priority is metadata on each concern — not a group wrapper.
     *
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

    /**
     * @param  iterable<RepairOrderConcern>  $concerns
     * @return \Illuminate\Support\Collection<int, RepairOrderConcern>
     */
    public static function sortedModels(iterable $concerns): \Illuminate\Support\Collection
    {
        $groupOrder = self::pdfGroupOrder();

        return collect($concerns)
            ->sort(function (RepairOrderConcern $left, RepairOrderConcern $right) use ($groupOrder): int {
                $leftOrder = $groupOrder[$left->recommendationIntent()->value] ?? 99;
                $rightOrder = $groupOrder[$right->recommendationIntent()->value] ?? 99;

                if ($leftOrder !== $rightOrder) {
                    return $leftOrder <=> $rightOrder;
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
     * Customer PDF / portal — Diagnostic first, then same types stay together.
     *
     * @param  list<array<string, mixed>>|iterable<mixed>  $concerns
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public static function sortedSnapshotConcerns(iterable $concerns): \Illuminate\Support\Collection
    {
        $groupOrder = self::pdfGroupOrder();

        return collect($concerns)
            ->filter(fn ($concern): bool => is_array($concern))
            ->sort(function (array $left, array $right) use ($groupOrder): int {
                $leftIntent = self::fromStored((string) ($left['recommendation_intent'] ?? null))->value;
                $rightIntent = self::fromStored((string) ($right['recommendation_intent'] ?? null))->value;
                $leftOrder = $groupOrder[$leftIntent] ?? 99;
                $rightOrder = $groupOrder[$rightIntent] ?? 99;

                if ($leftOrder !== $rightOrder) {
                    return $leftOrder <=> $rightOrder;
                }

                return ((int) ($left['position'] ?? 0)) <=> ((int) ($right['position'] ?? 0));
            })
            ->values();
    }
}
