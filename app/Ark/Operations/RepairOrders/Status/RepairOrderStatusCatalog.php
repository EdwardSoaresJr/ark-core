<?php

namespace App\Ark\Operations\RepairOrders\Status;

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkflowStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class RepairOrderStatusCatalog
{
    private const CACHE_KEY = 'repair_order_status_catalog.v4';

    /** @var array<string, RepairOrderStatusDefinition>|null */
    private ?array $statusesBySlug = null;

    /** @var array<string, list<array{to: string, roles: list<string>}>>|null */
    private ?array $transitionsByFrom = null;

    /** @var array<string, RepairOrderStatusVariant>|null */
    private ?array $variantsByKey = null;

    private ?bool $booted = null;

    public function isBooted(): bool
    {
        return $this->booted ??= RepairOrderStatusDefinition::query()->exists();
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('repair_order_status_catalog.v2');
        Cache::forget('repair_order_status_catalog.v1');
        $this->statusesBySlug = null;
        $this->transitionsByFrom = null;
        $this->variantsByKey = null;
        $this->booted = null;
    }

    public function definitionForSlug(string $slug): ?RepairOrderStatusDefinition
    {
        $this->bootIfNeeded();

        return $this->statusesBySlug[$slug] ?? null;
    }

    public function labelForSlug(string $slug): string
    {
        return $this->definitionForSlug($slug)?->name
            ?? (RepairOrderStatus::tryFrom($slug)?->label() ?? ucwords(str_replace('_', ' ', $slug)));
    }

    public function isTerminalSlug(string $slug): bool
    {
        if ($definition = $this->definitionForSlug($slug)) {
            return $definition->is_terminal;
        }

        return RepairOrderStatus::tryFrom($slug)?->isTerminal() ?? false;
    }

    public function requiresCloseVariant(string $slug): bool
    {
        return $this->definitionForSlug($slug)?->requires_variant ?? false;
    }

    public function variant(string $statusSlug, string $variantKey): ?RepairOrderStatusVariant
    {
        $this->bootIfNeeded();

        return $this->variantsByKey["{$statusSlug}:{$variantKey}"] ?? null;
    }

    /**
     * @return list<RepairOrderStatusVariant>
     */
    public function variantsForStatus(string $statusSlug): array
    {
        $this->bootIfNeeded();

        return collect($this->variantsByKey ?? [])
            ->filter(fn (RepairOrderStatusVariant $variant): bool => $variant->status_slug === $statusSlug)
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function advisorBoardSlugs(): array
    {
        $this->bootIfNeeded();

        return collect($this->statusesBySlug ?? [])
            ->filter(fn (RepairOrderStatusDefinition $status): bool => $status->active && ! $status->is_terminal && $status->show_on_advisor_board)
            ->sortBy('sort_order')
            ->pluck('slug')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function technicianBoardSlugs(): array
    {
        $this->bootIfNeeded();

        return collect($this->statusesBySlug ?? [])
            ->filter(fn (RepairOrderStatusDefinition $status): bool => $status->active && ! $status->is_terminal && $status->show_on_technician_board)
            ->sortBy('sort_order')
            ->pluck('slug')
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     label: string,
     *     description: string,
     *     tone: string,
     *     include_encounters?: bool,
     *     statuses: list<string>
     * }>
     */
    public function advisorWorkboardLanes(): array
    {
        if (! $this->isBooted()) {
            return [];
        }

        $this->bootIfNeeded();

        $boardSlugs = array_flip($this->advisorBoardSlugs());
        $templates = collect(RepairOrderStatusCatalogDefaults::advisorLaneTemplates())->keyBy('key');
        $slugsByLane = [];
        $customOwnLanes = [];

        foreach ($this->statusesBySlug ?? [] as $slug => $definition) {
            if (! isset($boardSlugs[$slug])) {
                continue;
            }

            $laneKey = $definition->advisor_lane_key ?? $slug;

            if ($templates->has($laneKey)) {
                $slugsByLane[$laneKey][] = $slug;
            } else {
                $customOwnLanes[$laneKey][] = $slug;
            }
        }

        $lanes = [];

        foreach ($templates as $laneKey => $template) {
            $slugs = $slugsByLane[$laneKey] ?? [];

            if ($slugs === []) {
                continue;
            }

            $lanes[] = [
                'label' => $template['label'],
                'description' => $template['description'],
                'tone' => $template['tone'],
                'statuses' => $slugs,
            ];
        }

        foreach ($customOwnLanes as $laneKey => $slugs) {
            $lanes[] = [
                'label' => $this->labelForSlug($slugs[0]),
                'description' => 'Custom workflow status',
                'tone' => 'motion',
                'statuses' => $slugs,
            ];
        }

        return $lanes;
    }

    /**
     * @return list<array{
     *     label: string,
     *     description: string,
     *     tone: string,
     *     statuses: list<RepairOrderStatus>
     * }>
     */
    public function technicianWorkboardLanes(): array
    {
        return $this->resolveWorkboardLanes(
            RepairOrderStatusCatalogDefaults::technicianWorkboardLanes(),
            $this->technicianBoardSlugs(),
        );
    }

    /**
     * @param  list<array{
     *     label: string,
     *     description: string,
     *     tone: string,
     *     include_encounters?: bool,
     *     slugs: list<string>
     * }>  $lanes
     * @param  list<string>  $allowedSlugs
     * @return list<array{
     *     label: string,
     *     description: string,
     *     tone: string,
     *     include_encounters?: bool,
     *     statuses: list<RepairOrderStatus>
     * }>
     */
    private function resolveWorkboardLanes(array $lanes, array $allowedSlugs): array
    {
        if (! $this->isBooted()) {
            return [];
        }

        $allowed = array_flip($allowedSlugs);

        return collect($lanes)
            ->map(function (array $lane) use ($allowed): array {
                $statuses = collect($lane['slugs'])
                    ->filter(fn (string $slug): bool => isset($allowed[$slug]))
                    ->map(fn (string $slug): ?RepairOrderStatus => RepairOrderStatus::tryFrom($slug))
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'label' => $lane['label'],
                    'description' => $lane['description'],
                    'tone' => $lane['tone'],
                    'include_encounters' => $lane['include_encounters'] ?? false,
                    'statuses' => $statuses,
                ];
            })
            ->filter(fn (array $lane): bool => $lane['statuses'] !== [] || ($lane['include_encounters'] ?? false))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function allowedTargetSlugs(string $fromSlug, ?User $actor = null): array
    {
        $fromSlug = RepairOrderWorkflowStatus::normalizeSlug($fromSlug);
        $this->bootIfNeeded();

        if ($this->statusesBySlug === []) {
            $fromEnum = RepairOrderStatus::tryFrom($fromSlug);

            if ($fromEnum === null) {
                return [];
            }

            return array_values(array_filter(array_map(
                static fn (RepairOrderStatus $status): string => $status->value,
                array_filter(
                    $fromEnum->legacyAllowedOperationalTransitions(),
                    static fn (RepairOrderStatus $status): bool => $status !== RepairOrderStatus::Closed,
                ),
            )));
        }

        $targets = [];

        foreach ($this->transitionsByFrom[$fromSlug] ?? [] as $transition) {
            if ($transition['to'] === RepairOrderStatus::Closed->value) {
                continue;
            }

            if ($actor !== null && ! $this->actorHasTransitionRole($actor, $transition['roles'])) {
                continue;
            }

            if (! ($this->statusesBySlug[$transition['to']]->active ?? false)) {
                continue;
            }

            $targets[] = $transition['to'];
        }

        $targets = array_values(array_unique($targets));
        $fromSortOrder = $this->sortOrderForSlug($fromSlug);

        usort($targets, function (string $a, string $b) use ($fromSortOrder): int {
            $aSort = $this->sortOrderForSlug($a);
            $bSort = $this->sortOrderForSlug($b);
            $aRetreat = $aSort < $fromSortOrder;
            $bRetreat = $bSort < $fromSortOrder;

            if ($aRetreat !== $bRetreat) {
                return $aRetreat ? -1 : 1;
            }

            return $aSort <=> $bSort;
        });

        return $targets;
    }

    public function sortOrderForSlug(string $slug): int
    {
        $slug = RepairOrderWorkflowStatus::normalizeSlug($slug);
        $this->bootIfNeeded();

        return $this->statusesBySlug[$slug]->sort_order ?? 999;
    }

    public function isRetreatTransition(string $fromSlug, string $toSlug): bool
    {
        return $this->sortOrderForSlug($toSlug) < $this->sortOrderForSlug($fromSlug);
    }

    /**
     * @return list<RepairOrderStatus>
     */
    public function allowedTargets(RepairOrderStatus|RepairOrderWorkflowStatus|string $fromStatus, ?User $actor = null): array
    {
        return array_values(array_filter(array_map(
            static fn (string $slug): ?RepairOrderStatus => RepairOrderStatus::tryFrom($slug),
            $this->allowedTargetSlugs($this->resolveSlug($fromStatus), $actor),
        )));
    }

    /**
     * @return list<RepairOrderStatusVariant>
     */
    public function allowedCloseVariants(RepairOrderStatus|RepairOrderWorkflowStatus|string $fromStatus, ?User $actor = null): array
    {
        return array_values(array_filter(
            $this->variantsForStatus(RepairOrderStatus::Closed->value),
            fn (RepairOrderStatusVariant $variant): bool => $this->canClose($fromStatus, $actor, $variant->variant_key),
        ));
    }

    public function canTransitionSlug(
        string $fromSlug,
        string $toSlug,
        ?User $actor = null,
        ?string $closeVariantKey = null,
        ?array $actorRoleNames = null,
    ): bool {
        $fromSlug = RepairOrderWorkflowStatus::normalizeSlug($fromSlug);
        $toSlug = RepairOrderWorkflowStatus::normalizeSlug($toSlug);

        if ($fromSlug === $toSlug) {
            return false;
        }

        if ($toSlug === RepairOrderStatus::Closed->value) {
            $fromEnum = RepairOrderStatus::tryFrom($fromSlug);

            return $fromEnum !== null
                && $this->canClose($fromEnum, $actor, $closeVariantKey, $actorRoleNames);
        }

        $this->bootIfNeeded();

        if ($this->statusesBySlug === []) {
            $fromEnum = RepairOrderStatus::tryFrom($fromSlug);
            $toEnum = RepairOrderStatus::tryFrom($toSlug);

            return $fromEnum !== null
                && $toEnum !== null
                && in_array($toEnum, $fromEnum->legacyAllowedOperationalTransitions(), true);
        }

        foreach ($this->transitionsByFrom[$fromSlug] ?? [] as $transition) {
            if ($transition['to'] !== $toSlug) {
                continue;
            }

            if ($actor === null) {
                return true;
            }

            return $this->actorHasTransitionRole($actor, $transition['roles'], $actorRoleNames);
        }

        return false;
    }

    public function canTransition(
        RepairOrderStatus|RepairOrderWorkflowStatus|string $fromStatus,
        RepairOrderStatus|RepairOrderWorkflowStatus|string $toStatus,
        ?User $actor = null,
        ?string $closeVariantKey = null,
        ?array $actorRoleNames = null,
    ): bool {
        return $this->canTransitionSlug(
            $this->resolveSlug($fromStatus),
            $this->resolveSlug($toStatus),
            $actor,
            $closeVariantKey,
            $actorRoleNames,
        );
    }

    public function canClose(
        RepairOrderStatus|RepairOrderWorkflowStatus|string $fromStatus,
        ?User $actor,
        ?string $closeVariantKey,
        ?array $actorRoleNames = null,
    ): bool {
        $fromSlug = $this->resolveSlug($fromStatus);

        if ($this->isTerminalSlug($fromSlug)) {
            return false;
        }

        if ($closeVariantKey === null) {
            return false;
        }

        $this->bootIfNeeded();

        $variant = $this->variant(RepairOrderStatus::Closed->value, $closeVariantKey);

        if ($variant === null) {
            return false;
        }

        if ($actor === null) {
            return true;
        }

        if ($variant->bypass_standard_close_rules) {
            return $this->actorCanCloseBypassVariant($fromSlug, $actor, $actorRoleNames);
        }

        if ($this->statusesBySlug === []) {
            return $this->actorHasAnyRole($actor, [ArkRole::Admin->value, ArkRole::Advisor->value], $actorRoleNames);
        }

        foreach ($this->transitionsByFrom[$fromSlug] ?? [] as $transition) {
            if ($transition['to'] !== RepairOrderStatus::Closed->value) {
                continue;
            }

            if ($this->actorHasTransitionRole($actor, $transition['roles'], $actorRoleNames)) {
                return true;
            }
        }

        return $this->actorCanClosePaidCompat($fromSlug, $actor, $actorRoleNames);
    }

    public function closeVariantBypassesRules(?string $closeVariantKey): bool
    {
        if ($closeVariantKey === null) {
            return false;
        }

        return $this->variant(RepairOrderStatus::Closed->value, $closeVariantKey)?->bypass_standard_close_rules ?? false;
    }

    public function closeVariantAffectsMetrics(?string $closeVariantKey): bool
    {
        if ($closeVariantKey === null) {
            return true;
        }

        return $this->variant(RepairOrderStatus::Closed->value, $closeVariantKey)?->affects_metrics ?? true;
    }

    public function displayLabel(string|RepairOrderWorkflowStatus $status, ?string $closeVariantKey): string
    {
        $slug = $status instanceof RepairOrderWorkflowStatus ? $status->value : $status;

        if ($slug === RepairOrderStatus::Closed->value && $closeVariantKey !== null) {
            $variant = $this->variant($slug, $closeVariantKey);

            if ($variant !== null) {
                return $this->labelForSlug($slug).' — '.$variant->name;
            }
        }

        return $this->labelForSlug($slug);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function filterOptions(): array
    {
        $this->bootIfNeeded();

        if ($this->statusesBySlug === []) {
            return array_map(
                static fn (RepairOrderStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                RepairOrderStatus::cases(),
            );
        }

        return collect($this->statusesBySlug)
            ->filter(fn (RepairOrderStatusDefinition $status): bool => $status->active)
            ->sortBy([['sort_order', 'asc'], ['slug', 'asc']])
            ->map(fn (RepairOrderStatusDefinition $status): array => [
                'value' => $status->slug,
                'label' => $status->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     dashboard_group_name: ?string,
     *     is_terminal: bool,
     *     active: bool,
     *     show_on_advisor_board: bool,
     *     show_on_technician_board: bool,
     *     variants: list<array{id: int, key: string, name: string, bypass_standard_close_rules: bool, affects_metrics: bool}>,
     *     transitions: list<array{id: ?int, form_key: string, to: string, to_name: string, active: bool, roles: list<string>}>
     * }>
     */
    public function settingsFormData(): array
    {
        if (! $this->isBooted()) {
            return [];
        }

        $this->bootIfNeeded();

        $statuses = RepairOrderStatusDefinition::query()
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get();

        $transitionsByFrom = RepairOrderStatusTransition::query()
            ->with('roles')
            ->get()
            ->groupBy('from_status_slug');

        return $statuses
            ->map(function (RepairOrderStatusDefinition $status) use ($statuses, $transitionsByFrom): array {
                $existingByTarget = ($transitionsByFrom[$status->slug] ?? collect())
                    ->keyBy('to_status_slug');

                $transitions = $statuses
                    ->reject(fn (RepairOrderStatusDefinition $target): bool => $target->slug === $status->slug)
                    ->map(function (RepairOrderStatusDefinition $target) use ($status, $existingByTarget): array {
                        /** @var RepairOrderStatusTransition|null $existing */
                        $existing = $existingByTarget->get($target->slug);

                        return [
                            'id' => $existing?->id,
                            'form_key' => $existing !== null
                                ? (string) $existing->id
                                : 'new:'.$status->slug.':'.$target->slug,
                            'to' => $target->slug,
                            'to_name' => $target->name,
                            'active' => $existing !== null
                                ? ($existing->active && $existing->roles->isNotEmpty())
                                : false,
                            'roles' => $existing !== null
                                ? $existing->roles->pluck('role')->values()->all()
                                : RepairOrderStatusCatalogDefaults::defaultRolesForTransition($status->slug, $target->slug),
                        ];
                    })
                    ->sortBy('to_name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all();

                $variants = RepairOrderStatusVariant::query()
                    ->where('status_slug', $status->slug)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (RepairOrderStatusVariant $variant): array => [
                        'id' => $variant->id,
                        'key' => $variant->variant_key,
                        'name' => $variant->name,
                        'bypass_standard_close_rules' => $variant->bypass_standard_close_rules,
                        'affects_metrics' => $variant->affects_metrics,
                    ])
                    ->values()
                    ->all();

                return [
                    'slug' => $status->slug,
                    'name' => $status->name,
                    'color' => RepairOrderStatusColor::normalize($status->color),
                    'color_label' => RepairOrderStatusColor::label($status->color),
                    'color_swatch' => RepairOrderStatusColor::swatch($status->color),
                    'chip_tone' => RepairOrderStatusColor::chipTone($status->color),
                    'dashboard_group_name' => $status->dashboard_group_name,
                    'advisor_lane_key' => $status->advisor_lane_key,
                    'is_terminal' => $status->is_terminal,
                    'is_system' => $status->is_system,
                    'active' => $status->active,
                    'show_on_advisor_board' => $status->show_on_advisor_board,
                    'show_on_technician_board' => $status->show_on_technician_board,
                    'variants' => $variants,
                    'transitions' => $transitions,
                ];
            })
            ->values()
            ->all();
    }

    public function colorForSlug(string $slug): string
    {
        return RepairOrderStatusColor::normalize($this->definitionForSlug($slug)?->color);
    }

    public function chipToneForSlug(string $slug): string
    {
        return RepairOrderStatusColor::chipTone($this->colorForSlug($slug));
    }

    public function boardToneForSlug(string $slug): string
    {
        return RepairOrderStatusColor::boardTone($this->colorForSlug($slug));
    }

    /**
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     dashboard_group_name: ?string,
     *     is_terminal: bool,
     *     active: bool,
     *     show_on_advisor_board: bool,
     *     variants: list<array{key: string, name: string, bypass_standard_close_rules: bool, affects_metrics: bool}>,
     *     transitions: list<array{to: string, to_name: string, roles: list<string>}>
     * }>
     */
    public function settingsOverview(): array
    {
        $this->bootIfNeeded();

        if ($this->statusesBySlug === []) {
            return [];
        }

        return collect($this->statusesBySlug)
            ->sortBy([['sort_order', 'asc'], ['slug', 'asc']])
            ->map(function (RepairOrderStatusDefinition $status): array {
                $transitions = collect($this->transitionsByFrom[$status->slug] ?? [])
                    ->map(fn (array $transition): array => [
                        'to' => $transition['to'],
                        'to_name' => $this->labelForSlug($transition['to']),
                        'roles' => $transition['roles'],
                    ])
                    ->sortBy('to_name')
                    ->values()
                    ->all();

                $variants = collect($this->variantsForStatus($status->slug))
                    ->map(fn (RepairOrderStatusVariant $variant): array => [
                        'key' => $variant->variant_key,
                        'name' => $variant->name,
                        'bypass_standard_close_rules' => $variant->bypass_standard_close_rules,
                        'affects_metrics' => $variant->affects_metrics,
                    ])
                    ->values()
                    ->all();

                return [
                    'slug' => $status->slug,
                    'name' => $status->name,
                    'dashboard_group_name' => $status->dashboard_group_name,
                    'is_terminal' => $status->is_terminal,
                    'active' => $status->active,
                    'show_on_advisor_board' => $status->show_on_advisor_board,
                    'variants' => $variants,
                    'transitions' => $transitions,
                ];
            })
            ->values()
            ->all();
    }

    private function actorCanCloseLost(User $actor, ?array $actorRoleNames = null): bool
    {
        return $this->actorHasAnyRole($actor, [
            ArkRole::Admin->value,
            ArkRole::Advisor->value,
        ], $actorRoleNames);
    }

    private function actorCanCloseBypassVariant(
        string $fromSlug,
        User $actor,
        ?array $actorRoleNames = null,
    ): bool {
        if ($this->statusesBySlug === []) {
            return $this->actorCanCloseLost($actor, $actorRoleNames);
        }

        foreach ($this->transitionsByFrom[$fromSlug] ?? [] as $transition) {
            if ($transition['to'] !== RepairOrderStatus::Closed->value) {
                continue;
            }

            if ($this->actorHasTransitionRole($actor, $transition['roles'], $actorRoleNames)) {
                return true;
            }
        }

        return $this->actorCanCloseLost($actor, $actorRoleNames);
    }

    private function actorCanClosePaidCompat(
        string $fromSlug,
        User $actor,
        ?array $actorRoleNames = null,
    ): bool {
        if (! $this->actorHasAnyRole($actor, [ArkRole::Admin->value, ArkRole::Advisor->value], $actorRoleNames)) {
            return false;
        }

        return RepairOrderWorkflowStatus::from($fromSlug)->isOneOf([
            RepairOrderStatus::ReadyPickup,
            RepairOrderStatus::Completed,
            RepairOrderStatus::Invoiced,
        ]);
    }

    private function resolveSlug(RepairOrderStatus|RepairOrderWorkflowStatus|string $status): string
    {
        return RepairOrderWorkflowStatus::from($status)->value;
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>|null  $actorRoleNames
     */
    private function actorHasTransitionRole(User $actor, array $roles, ?array $actorRoleNames = null): bool
    {
        return $this->actorHasAnyRole($actor, $roles, $actorRoleNames);
    }

    /**
     * @param  list<string>  $roles
     * @param  list<string>|null  $actorRoleNames
     */
    private function actorHasAnyRole(User $actor, array $roles, ?array $actorRoleNames = null): bool
    {
        if ($roles === []) {
            return true;
        }

        if ($actorRoleNames !== null) {
            return count(array_intersect($roles, $actorRoleNames)) > 0;
        }

        return $actor->hasAnyRole($roles);
    }

    /**
     * @param  list<RepairOrderStatus>  $statuses
     * @return list<RepairOrderStatus>
     */
    private function uniqueStatuses(array $statuses): array
    {
        $seen = [];

        return array_values(array_filter($statuses, function (RepairOrderStatus $status) use (&$seen): bool {
            if (isset($seen[$status->value])) {
                return false;
            }

            $seen[$status->value] = true;

            return true;
        }));
    }

    private function bootIfNeeded(): void
    {
        if ($this->statusesBySlug !== null) {
            return;
        }

        if (! $this->isBooted()) {
            $this->statusesBySlug = [];
            $this->transitionsByFrom = [];
            $this->variantsByKey = [];

            return;
        }

        $payload = Cache::rememberForever(self::CACHE_KEY, function (): array {
            $statuses = RepairOrderStatusDefinition::query()
                ->get()
                ->keyBy('slug')
                ->map(static fn (RepairOrderStatusDefinition $status): array => $status->getAttributes())
                ->all();

            $variants = RepairOrderStatusVariant::query()
                ->get()
                ->mapWithKeys(static fn (RepairOrderStatusVariant $variant): array => [
                    "{$variant->status_slug}:{$variant->variant_key}" => $variant->getAttributes(),
                ])
                ->all();

            $transitions = RepairOrderStatusTransition::query()
                ->with('roles')
                ->where('active', true)
                ->get()
                ->groupBy('from_status_slug')
                ->map(fn (Collection $rows): array => $rows->map(fn (RepairOrderStatusTransition $transition): array => [
                    'to' => $transition->to_status_slug,
                    'roles' => $transition->roles->pluck('role')->values()->all(),
                ])->values()->all())
                ->all();

            return [
                'statuses' => $statuses,
                'variants' => $variants,
                'transitions' => $transitions,
            ];
        });

        $this->statusesBySlug = array_map(
            fn (array $attributes): RepairOrderStatusDefinition => $this->hydrateStatusDefinition($attributes),
            $payload['statuses'],
        );

        $this->variantsByKey = array_map(
            fn (array $attributes): RepairOrderStatusVariant => $this->hydrateStatusVariant($attributes),
            $payload['variants'],
        );

        $this->transitionsByFrom = $payload['transitions'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function hydrateStatusDefinition(array $attributes): RepairOrderStatusDefinition
    {
        $status = new RepairOrderStatusDefinition;
        $status->forceFill($attributes);
        $status->exists = true;

        return $status;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function hydrateStatusVariant(array $attributes): RepairOrderStatusVariant
    {
        $variant = new RepairOrderStatusVariant;
        $variant->forceFill($attributes);
        $variant->exists = true;

        return $variant;
    }
}
