<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Vehicle workspace — the vehicle as a connected object with open work,
 * service history, and owner context. Money is advisor-only projection.
 */
final class MobileVehicleWorkspaceProjection
{
    public function __construct(
        private readonly MobileStaffAccess $access,
        private readonly MobileUserPresenter $userPresenter,
        private readonly MobileEstimateProjection $estimate,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forVehicle(Vehicle $vehicle, User $viewer): array
    {
        $vehicle->loadMissing(['customer']);
        $vehicle->load([
            'repairOrders' => fn ($query) => $query
                ->with(['concerns.lines'])
                ->latest()
                ->limit(16),
        ]);

        $profile = $this->userPresenter->repairOrderWorkspaceProfile($viewer);
        $showMoney = $profile !== 'technician';
        $repairOrders = $this->visibleRepairOrders($vehicle, $viewer);
        $openStatuses = RepairOrderStatus::operationalQueueValues();

        $openRepairOrders = $repairOrders
            ->filter(fn (RepairOrder $repairOrder): bool => in_array(
                $repairOrder->status->value,
                $openStatuses,
                true,
            ))
            ->values();

        $serviceHistory = $repairOrders
            ->reject(fn (RepairOrder $repairOrder): bool => in_array(
                $repairOrder->status->value,
                $openStatuses,
                true,
            ))
            ->take(8)
            ->values();

        /** @var RepairOrder|null $primaryOpen */
        $primaryOpen = $openRepairOrders->first();
        $customer = $vehicle->customer;
        $deferredWork = $this->deferredWork($openRepairOrders);
        $latestMileage = $repairOrders
            ->pluck('mileage_in')
            ->filter(fn (?int $mileage): bool => $mileage !== null && $mileage > 0)
            ->first();

        return [
            'vehicle' => [
                ...MobileIntakeVehicleProjection::summary($vehicle),
                'mileage_label' => $latestMileage !== null ? number_format($latestMileage).' mi' : null,
            ],
            'customer' => $customer !== null ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'display_phone' => $customer->display_phone,
            ] : null,
            'orientation' => $this->orientation($vehicle, $primaryOpen, $customer),
            'open_repair_orders' => $openRepairOrders
                ->map(fn (RepairOrder $repairOrder): array => $this->repairOrderRow($repairOrder, $showMoney))
                ->all(),
            'service_history' => $serviceHistory
                ->map(fn (RepairOrder $repairOrder): array => $this->repairOrderRow($repairOrder, $showMoney))
                ->all(),
            'engine_oil_history' => app(\App\Ark\Operations\Maintenance\VehicleEngineOilHistoryProjection::class)
                ->forVehicle((int) $vehicle->id),
            'deferred_work' => $deferredWork,
            'quick_actions' => $this->quickActions($vehicle, $customer, $primaryOpen, $viewer),
            'poll_after_seconds' => 45,
        ];
    }

    /**
     * @return Collection<int, RepairOrder>
     */
    private function visibleRepairOrders(Vehicle $vehicle, User $viewer): Collection
    {
        if ($this->access->canViewCustomer($viewer)) {
            return $vehicle->repairOrders;
        }

        return $vehicle->repairOrders
            ->filter(fn (RepairOrder $repairOrder): bool => (int) $repairOrder->assigned_technician_id === (int) $viewer->id)
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function repairOrderRow(RepairOrder $repairOrder, bool $showMoney): array
    {
        $money = $showMoney ? $this->estimate->summary($repairOrder) : null;

        return array_filter([
            'repair_order_id' => $repairOrder->repair_order_id,
            'number_label' => (string) $repairOrder->repair_order_id,
            'status' => $repairOrder->status->value,
            'status_label' => $repairOrder->status->label(),
            'concern_summary' => $repairOrder->concern_summary,
            'closed_at_label' => $repairOrder->closed_at?->diffForHumans(),
            'estimate_total_label' => $money['estimate_total_label'] ?? null,
            'balance_due_label' => $money['balance_due_label'] ?? null,
            'is_open' => in_array(
                $repairOrder->status->value,
                RepairOrderStatus::operationalQueueValues(),
                true,
            ),
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  Collection<int, RepairOrder>  $openRepairOrders
     * @return list<array<string, mixed>>
     */
    private function deferredWork(Collection $openRepairOrders): array
    {
        return $openRepairOrders
            ->flatMap(function (RepairOrder $repairOrder): Collection {
                return $repairOrder->concerns
                    ->filter(fn ($concern): bool => in_array(
                        $concern->disposition->value,
                        [
                            RepairOrderConcernDisposition::Recommended->value,
                            RepairOrderConcernDisposition::Deferred->value,
                        ],
                        true,
                    ))
                    ->map(fn ($concern): array => [
                        'repair_order_id' => $repairOrder->repair_order_id,
                        'concern_id' => $concern->id,
                        'title' => $concern->summary,
                        'disposition_label' => $concern->disposition->label(),
                    ]);
            })
            ->take(12)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function orientation(Vehicle $vehicle, ?RepairOrder $primaryOpen, ?Customer $customer): array
    {
        $situation = $primaryOpen !== null
            ? $primaryOpen->communicationPostureLabel()
            : 'No open work on this vehicle';

        $nextLabel = $primaryOpen !== null
            ? 'Open repair order'
            : ($customer !== null ? 'Start repair order' : 'Vehicle');

        return [
            'current_situation' => $situation,
            'next_best_action' => [
                'label' => $nextLabel,
                'key' => $primaryOpen !== null ? 'open_repair_order' : 'start_ro',
                'enabled' => $primaryOpen !== null || $customer !== null,
                'params' => array_filter([
                    'repair_order_id' => $primaryOpen?->repair_order_id,
                    'customer_id' => $customer?->id,
                    'vehicle_id' => $vehicle->id,
                ]),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function quickActions(Vehicle $vehicle, ?Customer $customer, ?RepairOrder $primaryOpen, User $viewer): array
    {
        $actions = [];

        if ($customer !== null && $this->access->canPerformIntake($viewer)) {
            $actions[] = [
                'key' => 'start_ro',
                'label' => 'Start RO',
                'enabled' => true,
                'params' => [
                    'customer_id' => $customer->id,
                    'vehicle_id' => $vehicle->id,
                ],
            ];
        }

        if ($primaryOpen !== null) {
            $actions[] = [
                'key' => 'open_repair_order',
                'label' => 'Open repair order',
                'enabled' => true,
                'params' => [
                    'repair_order_id' => $primaryOpen->repair_order_id,
                ],
            ];
        }

        if ($customer !== null && $this->access->canReplyToCustomer($viewer) && filled($customer->phone)) {
            $actions[] = [
                'key' => 'text_customer',
                'label' => 'Text customer',
                'enabled' => true,
                'params' => [
                    'customer_id' => $customer->id,
                    'repair_order_id' => $primaryOpen?->repair_order_id,
                ],
            ];
        }

        return $actions;
    }
}
