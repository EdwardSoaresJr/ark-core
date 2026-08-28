<?php

namespace App\Ark\Operations\RepairOrders\Status;

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RepairOrderStatusCatalogUpdater
{
    public function __construct(
        private readonly RepairOrderStatusCatalog $catalog,
    ) {}

    /**
     * @param  array{
     *     statuses?: array<string, array{name?: string, show_on_advisor_board?: mixed, show_on_technician_board?: mixed, advisor_lane_key?: string|null}>,
     *     variants?: array<int|string, array{name?: string}>,
     *     transitions?: array<int|string, array{active?: mixed, roles?: list<string>|null}>,
     *     create?: array{name?: string, slug?: string, color?: string|null, advisor_lane_key?: string|null, show_on_advisor_board?: mixed, show_on_technician_board?: mixed, from_slugs?: list<string>|null, to_slugs?: list<string>|null},
     *     create_transition?: array{from_slug?: string, to_slug?: string, roles?: list<string>|null}
     * }  $payload
     */
    public function apply(array $payload): void
    {
        if (($payload['create']['name'] ?? null) !== null) {
            $this->createStatus($payload['create']);
        }

        if (filled(trim((string) ($payload['create_transition']['from_slug'] ?? '')))) {
            $this->createTransition($payload['create_transition']);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($payload): void {
            foreach ($payload['statuses'] ?? [] as $slug => $statusPayload) {
                $this->updateStatus((string) $slug, $statusPayload);
            }

            foreach ($payload['variants'] ?? [] as $variantId => $variantPayload) {
                $this->updateVariant((int) $variantId, $variantPayload);
            }

            foreach ($payload['transitions'] ?? [] as $transitionKey => $transitionPayload) {
                $this->saveTransitionFromForm((string) $transitionKey, $transitionPayload);
            }
        });

        $this->catalog->forgetCache();
    }

    /**
     * @param  array{name?: string, slug?: string, advisor_lane_key?: string|null, show_on_advisor_board?: mixed, show_on_technician_board?: mixed, from_slugs?: list<string>|null, to_slugs?: list<string>|null}  $payload
     */
    public function createStatus(array $payload): RepairOrderStatusDefinition
    {
        $name = trim((string) ($payload['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages([
                'create.name' => 'Status name is required.',
            ]);
        }

        $slug = Str::slug(trim((string) ($payload['slug'] ?? $name)), '_');

        if ($slug === '' || strlen($slug) > 32) {
            throw ValidationException::withMessages([
                'create.slug' => 'Status slug must be 1–32 characters.',
            ]);
        }

        if (RepairOrderStatus::tryFrom($slug) !== null) {
            throw ValidationException::withMessages([
                'create.slug' => 'That slug is reserved for a built-in status.',
            ]);
        }

        if (RepairOrderStatusDefinition::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'create.slug' => 'That status slug already exists.',
            ]);
        }

        $laneKey = trim((string) ($payload['advisor_lane_key'] ?? 'shop_floor'));

        if ($laneKey === 'custom') {
            $laneKey = $slug;
        }

        if (! in_array($laneKey, RepairOrderStatusCatalogDefaults::advisorLaneKeys(), true) && $laneKey !== $slug) {
            throw ValidationException::withMessages([
                'create.advisor_lane_key' => 'Choose a valid advisor lane.',
            ]);
        }

        $maxSort = (int) RepairOrderStatusDefinition::query()->max('sort_order');

        $status = RepairOrderStatusDefinition::query()->create([
            'slug' => $slug,
            'name' => $name,
            'is_system' => false,
            'requires_mileage_in' => false,
            'requires_mileage_out' => false,
            'dashboard_group_slug' => 'custom',
            'dashboard_group_name' => 'Custom',
            'advisor_lane_key' => $laneKey,
            'show_on_advisor_board' => filter_var($payload['show_on_advisor_board'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'show_on_technician_board' => filter_var($payload['show_on_technician_board'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_terminal' => false,
            'requires_variant' => false,
            'enforce_standard_close_rules' => false,
            'active' => true,
            'sort_order' => $maxSort + 1,
            'customer_status_copy' => null,
            'color' => RepairOrderStatusColor::normalize($payload['color'] ?? RepairOrderStatusColor::SECONDARY),
        ]);

        $fromSlugs = collect($payload['from_slugs'] ?? ['in_progress', 'approved'])
            ->map(static fn (mixed $slug): string => (string) $slug)
            ->filter()
            ->unique()
            ->values();

        $toSlugs = collect($payload['to_slugs'] ?? ['in_progress', 'waiting_parts'])
            ->map(static fn (mixed $slug): string => (string) $slug)
            ->filter()
            ->unique()
            ->values();

        $defaultRoles = [ArkRole::Admin->value, ArkRole::Advisor->value];

        foreach ($fromSlugs as $fromSlug) {
            $this->ensureTransition($fromSlug, $slug, $defaultRoles);
        }

        foreach ($toSlugs as $toSlug) {
            $this->ensureTransition($slug, $toSlug, $defaultRoles);
        }

        $this->catalog->forgetCache();

        return $status;
    }

    /**
     * @param  array{from_slug?: string, to_slug?: string, roles?: list<string>|null}  $payload
     */
    public function createTransition(array $payload): RepairOrderStatusTransition
    {
        $fromSlug = trim((string) ($payload['from_slug'] ?? ''));
        $toSlug = trim((string) ($payload['to_slug'] ?? ''));

        if ($fromSlug === '' || $toSlug === '') {
            throw ValidationException::withMessages([
                'create_transition.from_slug' => 'Choose both a from and to status.',
            ]);
        }

        if ($fromSlug === $toSlug) {
            throw ValidationException::withMessages([
                'create_transition.to_slug' => 'From and to status must be different.',
            ]);
        }

        if (! RepairOrderStatusDefinition::query()->where('slug', $fromSlug)->exists()) {
            throw ValidationException::withMessages([
                'create_transition.from_slug' => 'Unknown from status.',
            ]);
        }

        if (! RepairOrderStatusDefinition::query()->where('slug', $toSlug)->exists()) {
            throw ValidationException::withMessages([
                'create_transition.to_slug' => 'Unknown to status.',
            ]);
        }

        $roles = collect($payload['roles'] ?? [ArkRole::Admin->value, ArkRole::Advisor->value])
            ->map(static fn (mixed $role): string => (string) $role)
            ->filter(static fn (string $role): bool => in_array($role, [
                ArkRole::Admin->value,
                ArkRole::Advisor->value,
                ArkRole::Technician->value,
            ], true))
            ->unique()
            ->values()
            ->all();

        if ($roles === []) {
            throw ValidationException::withMessages([
                'create_transition.roles' => 'Choose at least one role that may use this move.',
            ]);
        }

        $this->ensureTransition($fromSlug, $toSlug, $roles);

        return RepairOrderStatusTransition::query()
            ->where('from_status_slug', $fromSlug)
            ->where('to_status_slug', $toSlug)
            ->firstOrFail();
    }

    /**
     * @param  array{name?: string, color?: string|null, show_on_advisor_board?: mixed, show_on_technician_board?: mixed, advisor_lane_key?: string|null}  $payload
     */
    private function updateStatus(string $slug, array $payload): void
    {
        $status = RepairOrderStatusDefinition::query()->where('slug', $slug)->first();

        if ($status === null) {
            throw ValidationException::withMessages([
                'statuses' => "Unknown status slug [{$slug}].",
            ]);
        }

        if ($status->is_system && isset($payload['name']) && trim((string) $payload['name']) === '') {
            throw ValidationException::withMessages([
                "statuses.{$slug}.name" => 'Status name is required.',
            ]);
        }

        if (! $status->is_system && array_key_exists('advisor_lane_key', $payload)) {
            $laneKey = trim((string) ($payload['advisor_lane_key']));

            if ($laneKey === 'custom') {
                $laneKey = $status->slug;
            }

            if ($laneKey !== '' && (in_array($laneKey, RepairOrderStatusCatalogDefaults::advisorLaneKeys(), true) || $laneKey === $status->slug)) {
                $status->advisor_lane_key = $laneKey;
            }
        }

        if (array_key_exists('color', $payload) && filled($payload['color'])) {
            $color = strtolower(trim((string) $payload['color']));

            if (! in_array($color, RepairOrderStatusColor::keys(), true)) {
                throw ValidationException::withMessages([
                    "statuses.{$slug}.color" => 'Choose a valid status color.',
                ]);
            }

            $status->color = $color;
        }

        $status->fill([
            'name' => isset($payload['name']) ? trim((string) $payload['name']) : $status->name,
            'show_on_advisor_board' => array_key_exists('show_on_advisor_board', $payload)
                ? filter_var($payload['show_on_advisor_board'], FILTER_VALIDATE_BOOLEAN)
                : $status->show_on_advisor_board,
            'show_on_technician_board' => array_key_exists('show_on_technician_board', $payload)
                ? filter_var($payload['show_on_technician_board'], FILTER_VALIDATE_BOOLEAN)
                : $status->show_on_technician_board,
        ]);

        if ($status->is_terminal) {
            $status->show_on_advisor_board = false;
            $status->show_on_technician_board = false;
        }

        $status->save();
    }

    /**
     * @param  array{name?: string}  $payload
     */
    private function updateVariant(int $variantId, array $payload): void
    {
        $variant = RepairOrderStatusVariant::query()->find($variantId);

        if ($variant === null) {
            return;
        }

        if (isset($payload['name'])) {
            $name = trim((string) $payload['name']);

            if ($name === '') {
                throw ValidationException::withMessages([
                    "variants.{$variantId}.name" => 'Variant name is required.',
                ]);
            }

            $variant->update(['name' => $name]);
        }
    }

    /**
     * @param  array{active?: mixed, roles?: list<string>|null}  $payload
     */
    private function saveTransitionFromForm(string $transitionKey, array $payload): void
    {
        if (str_starts_with($transitionKey, 'new:')) {
            $parts = explode(':', $transitionKey, 3);

            if (count($parts) !== 3) {
                return;
            }

            [, $fromSlug, $toSlug] = $parts;

            if ($fromSlug === $toSlug) {
                return;
            }

            $roles = $this->normalizedTransitionRoles($payload['roles'] ?? []);

            if ($roles === []) {
                return;
            }

            $this->ensureTransition($fromSlug, $toSlug, $roles, true);

            return;
        }

        if (! ctype_digit($transitionKey)) {
            return;
        }

        $this->updateTransition((int) $transitionKey, $payload);
    }

    /**
     * @param  array{active?: mixed, roles?: list<string>|null}  $payload
     */
    private function updateTransition(int $transitionId, array $payload): void
    {
        $transition = RepairOrderStatusTransition::query()->with('roles')->find($transitionId);

        if ($transition === null) {
            return;
        }

        if (! array_key_exists('roles', $payload)) {
            return;
        }

        $roles = $this->normalizedTransitionRoles($payload['roles'] ?? []);
        $active = $roles !== [];

        $transition->update(['active' => $active]);
        $transition->roles()->delete();

        foreach ($roles as $role) {
            RepairOrderStatusTransitionRole::query()->create([
                'transition_id' => $transition->id,
                'role' => $role,
            ]);
        }
    }

    /**
     * @param  list<string>|null  $roles
     * @return list<string>
     */
    private function normalizedTransitionRoles(?array $roles): array
    {
        return collect($roles ?? [])
            ->map(static fn (mixed $role): string => (string) $role)
            ->filter(static fn (string $role): bool => in_array($role, [
                ArkRole::Admin->value,
                ArkRole::Advisor->value,
                ArkRole::Technician->value,
            ], true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $roles
     */
    private function ensureTransition(string $fromSlug, string $toSlug, array $roles, bool $active = true): void
    {
        if (! RepairOrderStatusDefinition::query()->where('slug', $fromSlug)->exists()
            || ! RepairOrderStatusDefinition::query()->where('slug', $toSlug)->exists()) {
            return;
        }

        $transition = RepairOrderStatusTransition::query()->updateOrCreate(
            [
                'from_status_slug' => $fromSlug,
                'to_status_slug' => $toSlug,
            ],
            ['active' => $active],
        );

        if ($transition->active !== $active) {
            $transition->update(['active' => $active]);
        }

        $transition->roles()->delete();

        foreach ($roles as $role) {
            RepairOrderStatusTransitionRole::query()->create([
                'transition_id' => $transition->id,
                'role' => $role,
            ]);
        }
    }
}
