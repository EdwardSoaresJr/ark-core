<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Runtime\Authorization\DevRolePretend;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class WorkboardLens
{
    public const ADVISOR = 'advisor';

    public const TECHNICIAN = 'technician';

    public static function forUser(?User $user): string
    {
        if ($user === null) {
            return self::ADVISOR;
        }

        if (DevRolePretend::isActive()) {
            return self::TECHNICIAN;
        }

        if ($user->hasAnyRole([ArkRole::Admin->value, ArkRole::Advisor->value])) {
            return self::ADVISOR;
        }

        if ($user->hasRole(ArkRole::Technician->value)) {
            return self::TECHNICIAN;
        }

        return self::ADVISOR;
    }

    public static function canToggleLens(User $user): bool
    {
        return $user->hasAnyRole([ArkRole::Admin->value, ArkRole::Advisor->value]);
    }

    /**
     * @return Collection<int, User>
     */
    public static function activeTechnicians(): Collection
    {
        return User::query()
            ->active()
            ->whereHas('roles', fn (Builder $query): Builder => $query->where('name', ArkRole::Technician->value))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return list<string>
     */
    public static function intakeQueueStatusValues(): array
    {
        return [
            RepairOrderStatus::Draft->value,
            RepairOrderStatus::Estimate->value,
        ];
    }

    /**
     * @return list<array{
     *     label: string,
     *     description: string,
     *     tone: string,
     *     statuses: list<RepairOrderStatus>
     * }>
     */
    public static function intakePressureBands(): array
    {
        return [
            [
                'label' => 'Needs Diagnosis',
                'description' => 'Recognition and qualification before estimate lines',
                'tone' => 'move',
                'statuses' => [
                    RepairOrderStatus::Draft,
                ],
            ],
            [
                'label' => 'Building Estimate',
                'description' => 'Qualified scopes — add lines and prepare authorization',
                'tone' => 'motion',
                'statuses' => [
                    RepairOrderStatus::Estimate,
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function queueStatusValues(string $lens): array
    {
        $catalog = app(RepairOrderStatusCatalog::class);

        if ($lens === self::TECHNICIAN && $catalog->isBooted()) {
            return $catalog->technicianBoardSlugs();
        }

        return collect(self::pressureBands($lens === self::TECHNICIAN ? self::TECHNICIAN : self::ADVISOR))
            ->flatMap(fn (array $band): array => $band['statuses'])
            ->map(fn (RepairOrderStatus|string $status): string => $status instanceof RepairOrderStatus ? $status->value : (string) $status)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return Collection<int, RepairOrder>
     */
    public static function filterRepairOrders(Collection $repairOrders, string $lens, User $user): Collection
    {
        if ($lens !== self::TECHNICIAN) {
            return $repairOrders;
        }

        $catalog = app(RepairOrderStatusCatalog::class);
        $allowedSlugs = $catalog->isBooted()
            ? $catalog->technicianBoardSlugs()
            : [
                RepairOrderStatus::Approved->value,
                RepairOrderStatus::ReadyForWork->value,
                RepairOrderStatus::WaitingParts->value,
                RepairOrderStatus::InProgress->value,
                RepairOrderStatus::QualityCheck->value,
            ];

        return $repairOrders
            ->filter(function (RepairOrder $repairOrder) use ($allowedSlugs, $user): bool {
                $laneStatus = $repairOrder->workboardLaneStatus()->value;

                if (! in_array($laneStatus, $allowedSlugs, true)) {
                    return false;
                }

                return match ($laneStatus) {
                    RepairOrderStatus::InProgress->value => self::technicianOwnsWorkOn($repairOrder, $user),
                    RepairOrderStatus::Approved->value,
                    RepairOrderStatus::ReadyForWork->value => ! $repairOrder->hasRepairActionOwner()
                        || self::technicianOwnsWorkOn($repairOrder, $user),
                    default => true,
                };
            })
            ->values();
    }

    private static function technicianOwnsWorkOn(RepairOrder $repairOrder, User $user): bool
    {
        $repairOrder->loadMissing('concerns.workGroups');

        return $repairOrder->concerns
            ->flatMap(fn (RepairOrderConcern $concern) => $concern->workGroups)
            ->contains(fn (RepairOrderWorkGroup $group): bool => $group->isOwnedByUserId((int) $user->id));
    }

    /**
     * @return list<array{
     *     label: string,
     *     description: string,
     *     tone: string,
     *     include_encounters?: bool,
     *     statuses: list<RepairOrderStatus>
     * }>
     */
    public static function pressureBands(string $lens): array
    {
        $catalog = app(RepairOrderStatusCatalog::class);

        if ($catalog->isBooted()) {
            $lanes = $lens === self::TECHNICIAN
                ? $catalog->technicianWorkboardLanes()
                : $catalog->advisorWorkboardLanes();

            if ($lanes !== []) {
                return $lanes;
            }
        }

        return self::legacyPressureBands($lens);
    }

    /**
     * @return list<array{
     *     label: string,
     *     description: string,
     *     tone: string,
     *     include_encounters?: bool,
     *     statuses: list<RepairOrderStatus>
     * }>
     */
    private static function legacyPressureBands(string $lens): array
    {
        if ($lens === self::TECHNICIAN) {
            return [
                [
                    'label' => 'Ready to Start',
                    'description' => 'Approved work waiting for bay assignment',
                    'tone' => 'motion',
                    'statuses' => [
                        RepairOrderStatus::Approved,
                        RepairOrderStatus::ReadyForWork,
                    ],
                ],
                [
                    'label' => 'Waiting Parts',
                    'description' => 'Approved work blocked on procurement',
                    'tone' => 'blocked',
                    'statuses' => [RepairOrderStatus::WaitingParts],
                ],
                [
                    'label' => 'My Bay',
                    'description' => 'Assigned and actively in progress',
                    'tone' => 'motion',
                    'statuses' => [RepairOrderStatus::InProgress],
                ],
                [
                    'label' => 'Quality Check',
                    'description' => 'Final checks before advisor handoff',
                    'tone' => 'ready',
                    'statuses' => [RepairOrderStatus::QualityCheck],
                ],
            ];
        }

        return [
            [
                'label' => 'Waiting Approval',
                'description' => 'Customer authorization pressure',
                'tone' => 'approval',
                'statuses' => [
                    RepairOrderStatus::WaitingApproval,
                ],
            ],
            [
                'label' => 'Waiting Parts',
                'description' => 'Procurement blockers on approved work',
                'tone' => 'blocked',
                'statuses' => [RepairOrderStatus::WaitingParts],
            ],
            [
                'label' => 'Shop Floor',
                'description' => 'Authorized and active bay work in the building',
                'tone' => 'motion',
                'statuses' => [
                    RepairOrderStatus::Approved,
                    RepairOrderStatus::ReadyForWork,
                    RepairOrderStatus::InProgress,
                ],
            ],
            [
                'label' => 'Quality Check',
                'description' => 'Final checks before advisor handoff',
                'tone' => 'ready',
                'statuses' => [RepairOrderStatus::QualityCheck],
            ],
            [
                'label' => 'Ready Pickup',
                'description' => 'Complete, invoice, and pickup release',
                'tone' => 'ready',
                'statuses' => [
                    RepairOrderStatus::Completed,
                    RepairOrderStatus::Invoiced,
                    RepairOrderStatus::ReadyPickup,
                ],
            ],
        ];
    }
}
