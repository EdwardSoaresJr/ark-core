<?php

namespace App\Ark\Operations\RepairOrders\Status;

use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Runtime\Authorization\ArkRole;

final class RepairOrderStatusCatalogDefaults
{
    /**
     * @return list<string>
     */
    public static function advisorLaneKeys(): array
    {
        return [
            'waiting_approval',
            'waiting_parts',
            'shop_floor',
            'quality_check',
            'ready_pickup',
        ];
    }

    /**
     * @return list<array{key: string, label: string, description: string, tone: string}>
     */
    public static function advisorLaneTemplates(): array
    {
        return [
            [
                'key' => 'waiting_approval',
                'label' => 'Waiting Approval',
                'description' => 'Customer authorization pressure',
                'tone' => 'approval',
            ],
            [
                'key' => 'waiting_parts',
                'label' => 'Waiting Parts',
                'description' => 'Procurement blockers on approved work',
                'tone' => 'blocked',
            ],
            [
                'key' => 'shop_floor',
                'label' => 'Shop Floor',
                'description' => 'Authorized and active bay work in the building',
                'tone' => 'motion',
            ],
            [
                'key' => 'quality_check',
                'label' => 'Quality Check',
                'description' => 'Final checks before advisor handoff',
                'tone' => 'ready',
            ],
            [
                'key' => 'ready_pickup',
                'label' => 'Ready Pickup',
                'description' => 'Complete, invoice, and pickup release',
                'tone' => 'ready',
            ],
        ];
    }

    /**
     * @return list<array{
     *     label: string,
     *     description: string,
     *     tone: string,
     *     slugs: list<string>
     * }>
     */
    public static function advisorWorkboardLanes(): array
    {
        return [
            [
                'label' => 'Waiting Approval',
                'description' => 'Customer authorization pressure',
                'tone' => 'approval',
                'slugs' => [RepairOrderStatus::WaitingApproval->value],
            ],
            [
                'label' => 'Waiting Parts',
                'description' => 'Procurement blockers on approved work',
                'tone' => 'blocked',
                'slugs' => [RepairOrderStatus::WaitingParts->value],
            ],
            [
                'label' => 'Shop Floor',
                'description' => 'Authorized and active bay work in the building',
                'tone' => 'motion',
                'slugs' => [
                    RepairOrderStatus::Approved->value,
                    RepairOrderStatus::ReadyForWork->value,
                    RepairOrderStatus::InProgress->value,
                ],
            ],
            [
                'label' => 'Quality Check',
                'description' => 'Final checks before advisor handoff',
                'tone' => 'ready',
                'slugs' => [RepairOrderStatus::QualityCheck->value],
            ],
            [
                'label' => 'Ready Pickup',
                'description' => 'Complete, invoice, and pickup release',
                'tone' => 'ready',
                'slugs' => [
                    RepairOrderStatus::Completed->value,
                    RepairOrderStatus::Invoiced->value,
                    RepairOrderStatus::ReadyPickup->value,
                ],
            ],
        ];
    }

    /**
     * @return list<array{
     *     label: string,
     *     description: string,
     *     tone: string,
     *     slugs: list<string>
     * }>
     */
    public static function technicianWorkboardLanes(): array
    {
        return [
            [
                'label' => 'Ready to Start',
                'description' => 'Approved work waiting for bay assignment',
                'tone' => 'motion',
                'slugs' => [
                    RepairOrderStatus::Approved->value,
                    RepairOrderStatus::ReadyForWork->value,
                ],
            ],
            [
                'label' => 'Waiting Parts',
                'description' => 'Approved work blocked on procurement',
                'tone' => 'blocked',
                'slugs' => [RepairOrderStatus::WaitingParts->value],
            ],
            [
                'label' => 'My Bay',
                'description' => 'Assigned and actively in progress',
                'tone' => 'motion',
                'slugs' => [RepairOrderStatus::InProgress->value],
            ],
            [
                'label' => 'Quality Check',
                'description' => 'Final checks before advisor handoff',
                'tone' => 'ready',
                'slugs' => [RepairOrderStatus::QualityCheck->value],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function statusDefinitions(): array
    {
        return [
            self::status('draft', 'Draft', lane: null, group: 'new_arrivals_intake', groupName: 'Estimates', sort: 0, color: 'dark', customerCopy: 'We’re still putting together your estimate.', advisorBoard: false),
            self::status('estimate', 'Building Estimate', lane: null, group: 'new_arrivals_intake', groupName: 'Estimates', sort: 1, color: 'secondary', customerCopy: 'We’re preparing your estimate.', advisorBoard: false),
            self::status('waiting_approval', 'Waiting Approval', lane: 'waiting_approval', group: 'new_arrivals_intake', groupName: 'Estimates', sort: 2, color: 'warning', customerCopy: 'Your estimate is ready. Reply APPROVE to proceed or NO to decline.'),
            self::status('approved', 'Approved', lane: 'shop_floor', group: 'work_in_progress', groupName: 'Work in progress', sort: 3, color: 'primary', customerCopy: 'Your estimate is approved. We’re moving forward.', technicianBoard: true),
            self::status('waiting_parts', 'Waiting Parts', lane: 'waiting_parts', group: 'work_in_progress', groupName: 'Work in progress', sort: 4, color: 'info', customerCopy: 'We’re waiting on parts to arrive.', technicianBoard: true),
            self::status('in_progress', 'In Progress', lane: 'shop_floor', group: 'work_in_progress', groupName: 'Work in progress', sort: 5, color: 'primary', requiresMileageIn: true, customerCopy: 'Your vehicle is currently being worked on.', technicianBoard: true),
            self::status('quality_check', 'Quality Check', lane: 'quality_check', group: 'work_in_progress', groupName: 'Work in progress', sort: 6, color: 'info', requiresMileageOut: true, customerCopy: 'We’re wrapping up final checks.', technicianBoard: true),
            self::status('completed', 'Completed', lane: 'ready_pickup', group: 'finalizing-and-pickup', groupName: 'Finalizing & pickup', sort: 7, color: 'success', requiresMileageOut: true, customerCopy: 'Repairs are completed.'),
            self::status('invoiced', 'Invoiced', lane: 'ready_pickup', group: 'completed', groupName: 'Completed', sort: 8, color: 'success', customerCopy: 'Your invoice is ready.'),
            self::status('ready_pickup', 'Ready for Pickup', lane: 'ready_pickup', group: 'finalizing-and-pickup', groupName: 'Finalizing & pickup', sort: 9, color: 'success', requiresMileageOut: true, customerCopy: 'Your vehicle is ready for pickup.'),
            self::status('closed', 'Closed', lane: null, sort: 10, terminal: true, requiresVariant: true, enforceCloseRules: true, advisorBoard: false, color: 'dark', customerCopy: 'This visit is closed.'),
            self::status('ready_for_work', 'Ready for Work', lane: 'shop_floor', group: 'work_in_progress', groupName: 'Work in progress', sort: 13, color: 'primary', customerCopy: 'Approved work is ready for the bay.', technicianBoard: true),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function variantDefinitions(): array
    {
        return [
            [
                'status_slug' => 'closed',
                'variant_key' => 'paid',
                'name' => 'Paid',
                'affects_metrics' => true,
                'bypass_standard_close_rules' => false,
                'is_default' => true,
                'sort_order' => 0,
            ],
            [
                'status_slug' => 'closed',
                'variant_key' => 'lost',
                'name' => 'Lost',
                'affects_metrics' => false,
                'bypass_standard_close_rules' => true,
                'is_default' => false,
                'sort_order' => 1,
            ],
        ];
    }

    /**
     * @return list<array{from_status_slug: string, to_status_slug: string, roles: list<string>, active: bool}>
     */
    public static function transitionDefinitions(): array
    {
        $rows = [
            ['draft', 'estimate', ['admin', 'advisor']],
            ['draft', 'waiting_approval', ['admin']],
            ['draft', 'approved', ['admin']],
            ['draft', 'closed', ['admin']],
            ['estimate', 'draft', ['admin', 'advisor']],
            ['estimate', 'waiting_approval', ['advisor']],
            ['estimate', 'closed', ['admin', 'advisor']],
            ['waiting_approval', 'estimate', ['advisor']],
            ['waiting_approval', 'approved', ['advisor']],
            ['waiting_approval', 'closed', ['admin', 'advisor']],
            ['approved', 'waiting_parts', ['advisor']],
            ['approved', 'ready_for_work', ['advisor']],
            ['approved', 'in_progress', ['technician', 'admin', 'advisor']],
            ['ready_for_work', 'in_progress', ['technician', 'admin', 'advisor']],
            ['ready_for_work', 'waiting_parts', ['advisor']],
            ['waiting_parts', 'in_progress', ['technician', 'admin']],
            ['waiting_parts', 'approved', ['advisor']],
            ['waiting_parts', 'ready_for_work', ['advisor']],
            ['in_progress', 'waiting_parts', ['technician', 'advisor', 'admin']],
            ['in_progress', 'quality_check', ['technician', 'admin', 'advisor']],
            ['in_progress', 'ready_pickup', ['technician', 'admin']],
            ['in_progress', 'approved', ['advisor', 'admin']],
            ['quality_check', 'completed', ['technician', 'advisor']],
            ['quality_check', 'in_progress', ['technician', 'admin', 'advisor']],
            ['quality_check', 'ready_pickup', ['technician', 'advisor']],
            ['completed', 'invoiced', ['advisor']],
            ['completed', 'ready_pickup', ['advisor']],
            ['invoiced', 'ready_pickup', ['advisor']],
            ['invoiced', 'closed', ['admin']],
            ['ready_pickup', 'closed', ['admin', 'advisor']],
            ['ready_pickup', 'in_progress', ['technician', 'admin', 'advisor']],
            ['ready_pickup', 'invoiced', ['advisor']],
            ['ready_pickup', 'completed', ['advisor', 'admin']],
            ['approved', 'waiting_approval', ['advisor', 'admin']],
            ['in_progress', 'ready_for_work', ['advisor', 'admin', 'technician']],
            ['completed', 'quality_check', ['advisor', 'admin']],
            ['completed', 'in_progress', ['advisor', 'admin']],
            ['invoiced', 'completed', ['advisor', 'admin']],
            ['quality_check', 'approved', ['advisor', 'admin']],
            ['quality_check', 'waiting_parts', ['advisor', 'admin']],
        ];

        return array_map(
            static fn (array $row): array => [
                'from_status_slug' => $row[0],
                'to_status_slug' => $row[1],
                'roles' => $row[2],
                'active' => true,
            ],
            $rows,
        );
    }

    /**
     * @return list<string>
     */
    public static function defaultRolesForTransition(string $fromSlug, string $toSlug): array
    {
        foreach (self::transitionDefinitions() as $transition) {
            if ($transition['from_status_slug'] === $fromSlug && $transition['to_status_slug'] === $toSlug) {
                return $transition['roles'];
            }
        }

        $roles = [
            ArkRole::Admin->value,
            ArkRole::Advisor->value,
        ];

        if (self::technicianShouldHaveDefaultAccess($fromSlug, $toSlug)) {
            $roles[] = ArkRole::Technician->value;
        }

        return array_values(array_unique($roles));
    }

    /**
     * Historically created every status×status pair as active, which flooded
     * Job Board and lifecycle menus. Creation stopped; pruning is one-shot via
     * deactivateNonCanonicalTransitions() (migration / artisan).
     *
     * Custom statuses and Settings-enabled moves must not be wiped here.
     */
    public static function ensureFullTransitionMatrix(?RepairOrderStatusCatalog $catalog = null): void
    {
        $catalog?->forgetCache();
    }

    /**
     * Deactivate transitions between system statuses that are not in
     * transitionDefinitions(). Leaves custom-status edges and shop-added
     * rows that involve non-system statuses alone.
     */
    public static function deactivateNonCanonicalTransitions(?RepairOrderStatusCatalog $catalog = null): void
    {
        if (! RepairOrderStatusDefinition::query()->exists()) {
            return;
        }

        $canonical = [];

        foreach (self::transitionDefinitions() as $transition) {
            $canonical[$transition['from_status_slug'].'|'.$transition['to_status_slug']] = true;
        }

        $systemSlugs = collect(self::statusDefinitions())
            ->pluck('slug')
            ->flip()
            ->all();

        RepairOrderStatusTransition::query()
            ->where('active', true)
            ->orderBy('id')
            ->each(function (RepairOrderStatusTransition $row) use ($canonical, $systemSlugs): void {
                $fromIsSystem = isset($systemSlugs[$row->from_status_slug]);
                $toIsSystem = isset($systemSlugs[$row->to_status_slug]);

                if (! $fromIsSystem || ! $toIsSystem) {
                    return;
                }

                $key = $row->from_status_slug.'|'.$row->to_status_slug;

                if (! isset($canonical[$key])) {
                    $row->forceFill(['active' => false])->save();
                }
            });

        foreach (self::transitionDefinitions() as $transition) {
            $roles = $transition['roles'];
            unset($transition['roles']);

            $row = RepairOrderStatusTransition::query()->updateOrCreate(
                [
                    'from_status_slug' => $transition['from_status_slug'],
                    'to_status_slug' => $transition['to_status_slug'],
                ],
                array_merge($transition, ['active' => true]),
            );

            if ($row->roles()->count() === 0) {
                foreach ($roles as $role) {
                    RepairOrderStatusTransitionRole::query()->create([
                        'transition_id' => $row->id,
                        'role' => $role,
                    ]);
                }
            }
        }

        $catalog?->forgetCache();
    }

    private static function technicianShouldHaveDefaultAccess(string $fromSlug, string $toSlug): bool
    {
        foreach (self::transitionDefinitions() as $transition) {
            if ($transition['from_status_slug'] === $fromSlug
                && $transition['to_status_slug'] === $toSlug) {
                return in_array(ArkRole::Technician->value, $transition['roles'], true);
            }
        }

        $productionSlugs = [
            RepairOrderStatus::Approved->value,
            RepairOrderStatus::ReadyForWork->value,
            RepairOrderStatus::WaitingParts->value,
            RepairOrderStatus::InProgress->value,
            RepairOrderStatus::QualityCheck->value,
            RepairOrderStatus::ReadyPickup->value,
        ];

        $technicianTargets = [
            RepairOrderStatus::WaitingParts->value,
            RepairOrderStatus::InProgress->value,
            RepairOrderStatus::QualityCheck->value,
            RepairOrderStatus::ReadyForWork->value,
            RepairOrderStatus::Completed->value,
            RepairOrderStatus::ReadyPickup->value,
        ];

        return in_array($fromSlug, $productionSlugs, true)
            && in_array($toSlug, $technicianTargets, true);
    }

    public static function sync(RepairOrderStatusCatalog $catalog): void
    {
        foreach (self::statusDefinitions() as $definition) {
            RepairOrderStatusDefinition::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                $definition,
            );
        }

        foreach (self::variantDefinitions() as $variant) {
            RepairOrderStatusVariant::query()->updateOrCreate(
                [
                    'status_slug' => $variant['status_slug'],
                    'variant_key' => $variant['variant_key'],
                ],
                $variant,
            );
        }

        foreach (self::transitionDefinitions() as $transition) {
            $roles = $transition['roles'];
            unset($transition['roles']);

            $row = RepairOrderStatusTransition::query()->updateOrCreate(
                [
                    'from_status_slug' => $transition['from_status_slug'],
                    'to_status_slug' => $transition['to_status_slug'],
                ],
                $transition,
            );

            $row->roles()->delete();

            foreach ($roles as $role) {
                RepairOrderStatusTransitionRole::query()->create([
                    'transition_id' => $row->id,
                    'role' => $role,
                ]);
            }
        }

        // Do not recreate the full matrix — that flooded operational menus.
        // One-shot prune: deactivateNonCanonicalTransitions() via migration.

        $catalog->forgetCache();
    }

    /**
     * @return array<string, mixed>
     */
    private static function status(
        string $slug,
        string $name,
        ?string $lane,
        int $sort,
        string $group = '',
        string $groupName = '',
        bool $terminal = false,
        bool $requiresVariant = false,
        bool $enforceCloseRules = false,
        bool $advisorBoard = true,
        bool $technicianBoard = false,
        bool $requiresMileageIn = false,
        bool $requiresMileageOut = false,
        ?string $color = null,
        ?string $customerCopy = null,
    ): array {
        return [
            'slug' => $slug,
            'name' => $name,
            'is_system' => true,
            'requires_mileage_in' => $requiresMileageIn,
            'requires_mileage_out' => $requiresMileageOut,
            'dashboard_group_slug' => $group !== '' ? $group : null,
            'dashboard_group_name' => $groupName !== '' ? $groupName : null,
            'advisor_lane_key' => $lane,
            'show_on_advisor_board' => $advisorBoard && ! $terminal,
            'show_on_technician_board' => $technicianBoard && ! $terminal,
            'is_terminal' => $terminal,
            'requires_variant' => $requiresVariant,
            'enforce_standard_close_rules' => $enforceCloseRules,
            'active' => true,
            'sort_order' => $sort,
            'customer_status_copy' => $customerCopy,
            'color' => $color,
        ];
    }
}
