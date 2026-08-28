<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\RepairOrders\LaborDescriptionPresentation;
use App\Ark\Operations\RepairOrders\OperationalIdentityPresenter;
use App\Ark\Operations\RepairOrders\RepairActionStatus;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderLineWorksheetOrder;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\Settings\ShopSettings;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class OperationalSheetPresenter
{
    public function __construct(
        private readonly EstimateSnapshotBuilder $snapshotBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function intake(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing(['customer', 'vehicle', 'assignedTechnician', 'concerns']);

        $settings = ShopSettings::current();
        $printedAt = $this->printedAt();
        $identity = OperationalIdentityPresenter::forRepairOrder(
            $repairOrder,
            includeStaffPosture: false,
            customerFacing: true,
            documentLabel: 'Check In',
            preparedAt: $printedAt,
            includeReferral: false,
        );

        return [
            'sheet_type' => 'intake',
            'heading' => 'Keys / Check In',
            'title' => 'Check In Sheet',
            'shop' => $this->shopBlock($settings),
            'identity' => $identity,
            'repair_order_id' => $repairOrder->repair_order_id,
            'status_label' => $repairOrder->status->label(),
            'printed_at' => $this->printedAtDisplay(),
            'concern_summary' => $repairOrder->concern_summary,
            'staff' => $this->staffBlock($repairOrder),
            'advisor_name' => $repairOrder->serviceAdvisorName(),
            'intake_flags' => $this->intakeFlags($repairOrder->intakeQuickFlags()),
            'mileage' => $this->mileageBlock($repairOrder),
            'concerns' => $this->intakeConcerns($repairOrder),
        ];
    }

    /**
     * Technician worksheet from owned Repair Actions (packages), not a filtered RO dump.
     *
     * @return array<string, mixed>
     */
    public function tech(RepairOrder $repairOrder, ?User $owner = null): array
    {
        $repairOrder->loadMissing([
            'customer',
            'vehicle',
            'concerns.lines',
            'concerns.workGroups.lines',
            'concerns.workGroups.ownerUser',
        ]);

        $settings = ShopSettings::current();
        $packages = $this->techPackages($repairOrder, $owner);
        $flagHours = $this->formatQuantity(
            (float) collect($packages)->sum(fn (array $package): float => (float) $package['labor_hours_raw']),
        );
        $vehicle = $repairOrder->vehicle;
        $vin = $vehicle?->authoritativeVin();
        $mileageIn = $repairOrder->resolvedMileageIn();

        $technicianName = $owner?->name
            ?? $this->techPackagesOwnerFallbackName($packages)
            ?? 'Unassigned';

        $packagesForSheet = array_map(static function (array $package): array {
            unset($package['labor_hours_raw']);

            return $package;
        }, $packages);

        return [
            'sheet_type' => 'tech',
            'title' => 'Technician Work Order',
            'shop' => $this->shopBlock($settings),
            'repair_order_id' => $repairOrder->repair_order_id,
            'vehicle_label' => $vehicle?->display_name ?: 'Vehicle not set',
            'vin_display' => $this->techVinDisplay($vin),
            'mileage_display' => $mileageIn !== null ? number_format($mileageIn) : 'Not recorded',
            'printed_at' => $this->printedAtDisplay(),
            'technician_name' => $technicianName,
            'owner_user_id' => $owner?->id,
            'work_station_label' => 'Unassigned',
            'approved_flag_hours' => $flagHours,
            'total_labor_hours' => $flagHours,
            'packages' => $packagesForSheet,
            'concerns' => array_map(static fn (array $package): array => [
                'summary' => $package['title'],
                'work_notes' => $package['work_notes'],
                'labor' => $package['labor'],
                'parts' => $package['parts'],
                'labor_hours' => $package['labor_hours'],
            ], $packagesForSheet),
            'has_approved_work' => $packagesForSheet !== [],
        ];
    }

    /**
     * Distinct owners with at least one approved Repair Action package on this RO.
     *
     * @return Collection<int, User>
     */
    public function techSheetOwners(RepairOrder $repairOrder): Collection
    {
        $repairOrder->loadMissing(['concerns.workGroups.ownerUser', 'concerns.workGroups.lines']);

        return $this->approvedWorkGroups($repairOrder)
            ->filter(fn (RepairOrderWorkGroup $group): bool => $group->hasOwner())
            ->map(fn (RepairOrderWorkGroup $group): ?User => $group->ownerUser)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function techPackages(RepairOrder $repairOrder, ?User $owner): array
    {
        return $this->approvedWorkGroups($repairOrder)
            ->filter(function (RepairOrderWorkGroup $group) use ($owner): bool {
                if ($owner === null) {
                    return true;
                }

                return $group->isOwnedByUserId((int) $owner->id);
            })
            ->map(fn (RepairOrderWorkGroup $group): array => $this->techPackageBlock($group))
            ->filter(fn (array $package): bool => $package['labor'] !== [] || ($package['sublets'] ?? []) !== [] || $package['parts'] !== [] || $package['work_notes'] !== [])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, RepairOrderWorkGroup>
     */
    private function approvedWorkGroups(RepairOrder $repairOrder): Collection
    {
        return $repairOrder->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition === RepairOrderConcernDisposition::Approved)
            ->sortBy('position')
            ->flatMap(fn (RepairOrderConcern $concern): Collection => $concern->workGroups->sortBy('position')->values())
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function techPackageBlock(RepairOrderWorkGroup $workGroup): array
    {
        $concern = $workGroup->concern;
        $lines = RepairOrderLineWorksheetOrder::sort(
            $workGroup->lines->filter(fn (RepairOrderLine $line): bool => $line->shouldDisplayOnEstimateWorksheet())
        );

        $noteLines = $lines
            ->filter(fn (RepairOrderLine $line): bool => $line->type === RepairOrderLineType::Note && $line->isVisibleToTechnician())
            ->values();

        $nonNotes = $lines
            ->filter(fn (RepairOrderLine $line): bool => $line->type !== RepairOrderLineType::Note)
            ->values();

        $laborLines = $nonNotes
            ->filter(fn (RepairOrderLine $line): bool => $line->type->countsTowardFlagHours())
            ->values();

        $subletLines = $nonNotes
            ->filter(fn (RepairOrderLine $line): bool => $line->type === RepairOrderLineType::Sublet)
            ->values();

        $laborCount = LaborDescriptionPresentation::laborCountInGroup($workGroup->lines);

        $labor = $laborLines
            ->map(function (RepairOrderLine $line) use ($workGroup, $laborCount): array {
                $hours = $this->formatQuantity((float) $line->quantity);
                $suppress = LaborDescriptionPresentation::shouldSuppressWorksheetDescription(
                    $line,
                    $workGroup->title,
                    $laborCount,
                );

                if ($suppress) {
                    return [
                        'description' => $workGroup->title,
                        'quantity' => $hours,
                        'operation_title' => $workGroup->title,
                        'label' => sprintf('%s — %s hrs', $workGroup->title, $hours),
                        'hours_only_label' => sprintf('%s hrs', $hours),
                        'suppress_duplicate' => true,
                    ];
                }

                return [
                    'description' => $line->description,
                    'quantity' => $hours,
                    'operation_title' => $workGroup->title,
                    'label' => sprintf('%s — %s hrs', $line->description, $hours),
                    'hours_only_label' => sprintf('%s hrs', $hours),
                    'suppress_duplicate' => false,
                ];
            })
            ->all();

        $sublets = $subletLines
            ->map(function (RepairOrderLine $line): array {
                $description = (string) $line->description;

                return [
                    'description' => $description,
                    'vendor' => filled($line->vendor_name) ? (string) $line->vendor_name : null,
                    'label' => filled($line->vendor_name)
                        ? sprintf('%s · %s', $line->vendor_name, $description)
                        : $description,
                ];
            })
            ->all();

        $parts = $nonNotes
            ->filter(fn (RepairOrderLine $line): bool => $line->type === RepairOrderLineType::Part)
            ->values()
            ->map(function (RepairOrderLine $line): array {
                $quantity = $this->formatQuantity((float) $line->quantity);
                $partNumber = filled($line->part_number) ? (string) $line->part_number : null;
                $description = (string) $line->description;
                $label = $partNumber !== null
                    ? sprintf('%s · %s — Qty %s', $partNumber, $description, $quantity)
                    : sprintf('%s — Qty %s', $description, $quantity);

                return [
                    'part_number' => $partNumber,
                    'description' => $description,
                    'vendor' => filled($line->vendor_name) ? (string) $line->vendor_name : null,
                    'quantity' => $quantity,
                    'has_core' => (bool) $line->has_core,
                    'save_old_part' => (bool) $line->save_old_part,
                    'label' => $label,
                ];
            })
            ->all();

        $workNotes = [];

        if ($concern !== null && filled($concern->verified_findings)) {
            $workNotes[] = ['label' => 'Verified findings', 'description' => (string) $concern->verified_findings];
        }

        if ($concern !== null && filled($concern->dtcs_summary)) {
            $workNotes[] = ['label' => 'DTCs', 'description' => (string) $concern->dtcs_summary];
        }

        foreach ($noteLines as $noteLine) {
            $workNotes[] = [
                'label' => 'Work note',
                'description' => (string) $noteLine->description,
            ];
        }

        $hoursRaw = (float) $laborLines->sum(fn (RepairOrderLine $line): float => (float) $line->quantity);
        $status = $workGroup->status instanceof RepairActionStatus
            ? $workGroup->status
            : RepairActionStatus::Pending;

        return [
            'title' => $workGroup->title,
            'owner_name' => $workGroup->ownerUser?->name,
            'status' => $status->value,
            'status_label' => $status->label(),
            'latest_update' => $workGroup->latest_update,
            'updated_at' => $workGroup->updated_at?->timezone(config('app.timezone'))->format('g:i A'),
            'concern_summary' => $concern?->summary,
            'work_notes' => $workNotes,
            'labor' => $labor,
            'sublets' => $sublets,
            'parts' => $parts,
            'labor_hours' => $this->formatQuantity($hoursRaw),
            'labor_hours_raw' => $hoursRaw,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $packages
     */
    private function techPackagesOwnerFallbackName(array $packages): ?string
    {
        $names = collect($packages)
            ->pluck('owner_name')
            ->filter(fn ($name): bool => filled($name))
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return null;
        }

        if ($names->count() === 1) {
            return (string) $names->first();
        }

        return $names->take(2)->implode(', ');
    }

    /**
     * @return array<string, mixed>
     */
    private function shopBlock(ShopSettings $settings): array
    {
        return $this->snapshotBuilder->presentationLayers()['shop'];
    }

    /**
     * @param  list<string>  $flags
     * @return list<string>
     */
    private function intakeFlags(array $flags): array
    {
        return array_values(array_filter($flags));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function intakeConcerns(RepairOrder $repairOrder): array
    {
        if ($repairOrder->concerns->isEmpty()) {
            return [[
                'summary' => $repairOrder->concern_summary ?: 'Customer concern not captured yet.',
                'customer_states' => null,
                'recommendation_intent' => null,
            ]];
        }

        return $repairOrder->concerns
            ->filter(fn (RepairOrderConcern $concern): bool => $concern->disposition->visibleToCustomer())
            ->sortBy('position')
            ->values()
            ->map(fn (RepairOrderConcern $concern): array => [
                'summary' => $concern->summary,
                'customer_states' => filled($concern->customer_states) ? $concern->customer_states : null,
                'recommendation_intent' => $concern->recommendationIntent()->value,
                'recommendation_intent_label' => $concern->recommendationIntent()->staffLabel(),
            ])
            ->all();
    }

    private function techVinDisplay(?string $vin): string
    {
        if (! filled($vin)) {
            return 'VIN not on file';
        }

        $vin = strtoupper(trim($vin));

        if (strlen($vin) <= 8) {
            return $vin;
        }

        return '…'.substr($vin, -8);
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');
    }

    /**
     * @return array{
     *     advisor: array{value: ?string, prefilled: bool},
     *     technician: array{value: ?string, prefilled: bool},
     * }
     */
    private function staffBlock(RepairOrder $repairOrder): array
    {
        return [
            'advisor' => $this->staffCaptureField($repairOrder->serviceAdvisorName()),
            'technician' => $this->staffCaptureField($repairOrder->assignedTechnician?->name),
        ];
    }

    /**
     * @return array{value: ?string, prefilled: bool}
     */
    private function staffCaptureField(?string $name): array
    {
        return [
            'value' => filled($name) ? (string) $name : null,
            'prefilled' => filled($name),
        ];
    }

    /**
     * @return array{in: array{value: ?string, prefilled: bool}, out: array{value: ?string, prefilled: bool}}
     */
    private function mileageBlock(RepairOrder $repairOrder): array
    {
        return [
            'in' => $this->mileageCaptureField($repairOrder->resolvedMileageIn()),
            'out' => $this->mileageCaptureField($repairOrder->resolvedMileageOut()),
        ];
    }

    /**
     * @return array{value: ?string, prefilled: bool}
     */
    private function mileageCaptureField(?int $mileage): array
    {
        return [
            'value' => $mileage !== null ? number_format($mileage) : null,
            'prefilled' => $mileage !== null,
        ];
    }

    private function printedAtDisplay(): string
    {
        return $this->printedAt()
            ->timezone(config('app.display_timezone'))
            ->format('M j, Y g:i A');
    }

    private function printedAt(): Carbon
    {
        return now();
    }
}
