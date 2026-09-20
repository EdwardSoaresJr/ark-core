<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\OperationsFeatures;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\Inspections\InspectionObservedState;
use App\Ark\Operations\RepairOrders\PartsPressure;
use App\Ark\Operations\RepairOrders\RecommendationIntent;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Operational Context — progressive disclosure of work state inside a conversation thread.
 *
 * Companion informs. ARKv2 operates. Show only what reduces uncertainty right now.
 */
final class MobileOperationalContextProjection
{
    public function __construct(
        private readonly MobileEstimateProjection $estimateProjection,
    ) {}

    /**
     * @param  array<string, mixed>  $context  ConversationContextProjection payload
     * @return array<string, mixed>
     */
    public function forThread(?RepairOrder $primaryRo, array $context, ?Customer $customer = null): array
    {
        if ($primaryRo instanceof RepairOrder) {
            $primaryRo->loadMissing([
                'vehicle',
                'customer',
                'concerns',
                'inspection.items.photos',
                'assignedTechnician:id,name',
            ]);
            $customer ??= $primaryRo->customer;
        }

        $vehicle = $primaryRo?->vehicle ?? $this->vehicleFromContext($context);
        $cards = [];

        $pickup = $this->pickupCard($primaryRo);
        if ($pickup !== null) {
            $cards[] = $pickup;
        }

        $estimate = $this->estimateCard($primaryRo);
        if ($estimate !== null) {
            $cards[] = $estimate;
        }

        $parts = $this->partsCard($primaryRo);
        if ($parts !== null) {
            $cards[] = $parts;
        }

        $repairOrder = $this->repairOrderCard($primaryRo);
        if ($repairOrder !== null) {
            $cards[] = $repairOrder;
        }

        $inspection = $this->inspectionCard($primaryRo);
        if ($inspection !== null) {
            $cards[] = $inspection;
        }

        $appointment = $this->appointmentCard($primaryRo, $customer, $vehicle);
        if ($appointment !== null) {
            $cards[] = $appointment;
        }

        $warranty = $this->warrantyCard($primaryRo);
        if ($warranty !== null) {
            $cards[] = $warranty;
        }

        $vehicleCard = $this->vehicleCard($vehicle, $primaryRo, $customer);
        if ($vehicleCard !== null) {
            $cards[] = $vehicleCard;
        }

        $this->applyDefaultExpansion($cards);

        return [
            'cards' => $cards,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function vehicleFromContext(array $context): ?Vehicle
    {
        $vehicle = is_array($context['vehicle'] ?? null) ? $context['vehicle'] : null;
        $vehicleId = is_array($vehicle) ? ($vehicle['id'] ?? null) : null;

        if ($vehicleId === null) {
            return null;
        }

        return Vehicle::query()->find($vehicleId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function vehicleCard(?Vehicle $vehicle, ?RepairOrder $repairOrder, ?Customer $customer): ?array
    {
        if (! $vehicle instanceof Vehicle) {
            return null;
        }

        $lines = [];
        $mileage = $repairOrder?->resolvedMileageIn();

        if ($mileage !== null) {
            $lines[] = number_format($mileage).' mi';
        }

        $vin = $vehicle->authoritativeVin();
        if (filled($vin)) {
            $lines[] = 'VIN ending '.Str::upper(Str::substr($vin, -4));
        }

        if ($customer instanceof Customer && $customer->created_at instanceof Carbon) {
            $lines[] = 'Customer since '.$customer->created_at->format('Y');
        }

        return [
            'key' => 'vehicle',
            'title' => 'Vehicle',
            'headline' => trim("{$vehicle->year} {$vehicle->make} {$vehicle->model}"),
            'lines' => $lines,
            'action_label' => 'Open Vehicle',
            'deep_link' => MobileCompanionDeepLink::vehicle($vehicle->id),
            'priority' => 10,
            'default_expanded' => false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function repairOrderCard(?RepairOrder $repairOrder): ?array
    {
        if (! $repairOrder instanceof RepairOrder) {
            return null;
        }

        $openedAt = $repairOrder->displayOpenedAt();
        $lines = [
            $repairOrder->statusDisplayLabel(),
            'Opened '.$openedAt->diffForHumans(short: true),
        ];

        $advisor = $repairOrder->serviceAdvisorName();
        if (filled($advisor)) {
            $lines[] = 'Advisor: '.$advisor;
        }

        return [
            'key' => 'repair_order',
            'title' => 'Repair Order',
            'headline' => 'RO #'.$repairOrder->repair_order_id,
            'lines' => $lines,
            'action_label' => 'Open Repair Order',
            'deep_link' => MobileCompanionDeepLink::repairOrder((int) $repairOrder->repair_order_id),
            'priority' => 20,
            'default_expanded' => false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function estimateCard(?RepairOrder $repairOrder): ?array
    {
        if (! $repairOrder instanceof RepairOrder) {
            return null;
        }

        $status = RepairOrderStatus::fromSlug((string) $repairOrder->status);
        $money = $this->estimateProjection->summary($repairOrder);
        $estimateView = CommunicationEvent::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('event_type', OperationalCommunicationType::EstimateViewed)
            ->latest('occurred_at')
            ->first();

        $waitingApproval = RepairOrderStatus::isWaitingCustomerApproval($status);
        $hasEstimate = ($money['has_lines'] ?? false) === true;
        $viewed = $estimateView instanceof CommunicationEvent;

        if (! $waitingApproval && ! $viewed && ! ($money['has_unapproved_work'] ?? false) && ! $hasEstimate) {
            return null;
        }

        if (! $hasEstimate && ! $viewed) {
            return null;
        }

        $lines = [];
        if ($viewed && $estimateView->occurred_at instanceof Carbon) {
            $lines[] = 'Viewed '.$estimateView->occurred_at->diffForHumans(short: true);
        }

        if ($waitingApproval || ($money['has_unapproved_work'] ?? false)) {
            $lines[] = 'Not approved';
        } elseif ($viewed) {
            $lines[] = 'Approved or in workflow';
        }

        return [
            'key' => 'estimate',
            'title' => 'Estimate',
            'headline' => (string) ($money['estimate_total_label'] ?? 'Estimate ready'),
            'lines' => $lines,
            'action_label' => 'Open Estimate',
            'deep_link' => MobileCompanionDeepLink::repairOrder((int) $repairOrder->repair_order_id),
            'priority' => 30,
            'default_expanded' => false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function inspectionCard(?RepairOrder $repairOrder): ?array
    {
        if (! $repairOrder instanceof RepairOrder) {
            return null;
        }

        $inspection = $repairOrder->inspection;
        if (! $inspection instanceof Inspection) {
            return null;
        }

        $inspection->loadMissing('items.photos');
        $items = $inspection->items;
        $checked = $items->filter(
            fn (InspectionItem $item): bool => $item->observed_state !== InspectionObservedState::NotChecked,
        )->count();
        $total = $items->count();
        $photoCount = $items->sum(fn (InspectionItem $item): int => $item->photos->count());

        $concerns = $repairOrder->concerns;
        $immediate = $concerns->filter(
            fn (RepairOrderConcern $concern): bool => in_array(
                $concern->recommendationIntent(),
                [RecommendationIntent::ImmediateAttention, RecommendationIntent::Diagnostic],
                true,
            ),
        )->count();
        $future = $concerns->filter(
            fn (RepairOrderConcern $concern): bool => in_array(
                $concern->recommendationIntent(),
                [RecommendationIntent::PlanSoon, RecommendationIntent::Maintenance],
                true,
            ),
        )->count();

        $started = $inspection->started_at !== null || $checked > 0;
        $complete = $inspection->completed_at !== null || ($total > 0 && $checked >= $total);

        if (! $started && $photoCount === 0 && ! $complete) {
            return null;
        }

        $lines = [];
        if ($photoCount > 0) {
            $lines[] = $photoCount.' '.Str::plural('Photo', $photoCount);
        }
        if ($immediate > 0) {
            $lines[] = $immediate.' Immediate';
        }
        if ($future > 0) {
            $lines[] = $future.' Future';
        }

        return [
            'key' => 'inspection',
            'title' => 'Inspection',
            'headline' => $complete ? 'Inspection Complete' : 'Inspection in progress',
            'lines' => $lines,
            'action_label' => 'View Inspection',
            'deep_link' => MobileCompanionDeepLink::repairOrderInspection((int) $repairOrder->repair_order_id),
            'priority' => 40,
            'default_expanded' => false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function partsCard(?RepairOrder $repairOrder): ?array
    {
        if (! $repairOrder instanceof RepairOrder) {
            return null;
        }

        $pressure = $repairOrder->partsPressure();
        $status = RepairOrderStatus::fromSlug((string) $repairOrder->status);
        $waitingPartsStatus = $status === RepairOrderStatus::WaitingParts;

        if (! $pressure->showsChip() && ! $waitingPartsStatus) {
            return null;
        }

        if ($pressure === PartsPressure::AllPartsAvailable && ! $waitingPartsStatus) {
            return null;
        }

        $summary = $repairOrder->partsPressureSummary($pressure);
        $unresolved = $repairOrder->approvedPartLines()
            ->filter(fn ($line): bool => $line->hasUnresolvedProcurement());
        $first = $unresolved->first();

        $headline = match ($pressure) {
            PartsPressure::AllPartsAvailable => 'All Parts Available',
            default => $first !== null
                ? 'Waiting on '.Str::limit(trim((string) $first->description), 48)
                : $pressure->label(),
        };

        $lines = [];
        if ($first !== null) {
            $etaLabel = $first->procurementStateLabel();
            if ($etaLabel !== '') {
                $lines[] = $etaLabel;
            }
            if (filled($first->vendor_name)) {
                $lines[] = (string) $first->vendor_name;
            }
        } elseif ($summary !== null) {
            $lines[] = $summary;
        } else {
            $lines[] = $pressure->label();
        }

        return [
            'key' => 'parts',
            'title' => 'Parts',
            'headline' => $headline,
            'lines' => array_values(array_filter($lines)),
            'action_label' => 'Open Repair Order',
            'deep_link' => MobileCompanionDeepLink::repairOrder((int) $repairOrder->repair_order_id),
            'priority' => 50,
            'default_expanded' => false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function appointmentCard(?RepairOrder $repairOrder, ?Customer $customer, ?Vehicle $vehicle): ?array
    {
        if (! OperationsFeatures::appointmentsEnabled()) {
            return null;
        }

        if (! $customer instanceof Customer && ! $repairOrder instanceof RepairOrder) {
            return null;
        }

        $query = Appointment::query()
            ->whereIn('status', array_map(
                static fn (AppointmentStatus $status): string => $status->value,
                AppointmentStatus::activeToday(),
            ))
            ->where('starts_at', '>=', now()->startOfDay())
            ->orderBy('starts_at');

        if ($repairOrder instanceof RepairOrder) {
            $query->where(function ($builder) use ($repairOrder, $customer, $vehicle): void {
                $builder->where('repair_order_id', $repairOrder->id);
                if ($customer instanceof Customer) {
                    $builder->orWhere('customer_id', $customer->id);
                }
                if ($vehicle instanceof Vehicle) {
                    $builder->orWhere('vehicle_id', $vehicle->id);
                }
            });
        } elseif ($customer instanceof Customer) {
            $query->where('customer_id', $customer->id);
        }

        $appointment = $query->first();
        if (! $appointment instanceof Appointment) {
            return null;
        }

        $displayTimezone = config('app.display_timezone');
        $startsAt = $appointment->starts_at->timezone($displayTimezone);
        $when = $startsAt->isToday()
            ? 'Today'
            : ($startsAt->isTomorrow() ? 'Tomorrow' : $startsAt->format('M j'));

        return [
            'key' => 'appointment',
            'title' => 'Appointment',
            'headline' => $when,
            'lines' => [
                $startsAt->format('g:i A'),
                $appointment->status->label(),
            ],
            'action_label' => 'Open Schedule',
            'deep_link' => MobileCompanionDeepLink::schedule($startsAt->toDateString()),
            'priority' => 60,
            'default_expanded' => false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function warrantyCard(?RepairOrder $repairOrder): ?array
    {
        if (! $repairOrder instanceof RepairOrder || ! (bool) $repairOrder->warranty) {
            return null;
        }

        return [
            'key' => 'warranty',
            'title' => 'Warranty',
            'headline' => 'Warranty repair',
            'lines' => ['Warranty repair eligible'],
            'action_label' => 'Open Repair Order',
            'deep_link' => MobileCompanionDeepLink::repairOrder((int) $repairOrder->repair_order_id),
            'priority' => 70,
            'default_expanded' => false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pickupCard(?RepairOrder $repairOrder): ?array
    {
        if (! $repairOrder instanceof RepairOrder) {
            return null;
        }

        $status = RepairOrderStatus::fromSlug((string) $repairOrder->status);
        if (! in_array($status, [RepairOrderStatus::ReadyPickup, RepairOrderStatus::Completed], true)) {
            return null;
        }

        $money = $this->estimateProjection->summary($repairOrder);
        $lines = [$repairOrder->statusDisplayLabel()];
        if (($money['balance_due_outstanding'] ?? false) === true && filled($money['balance_due_label'] ?? null)) {
            $lines[] = 'Balance due '.$money['balance_due_label'];
        }

        return [
            'key' => 'pickup',
            'title' => 'Pickup',
            'headline' => 'Vehicle ready for pickup',
            'lines' => $lines,
            'action_label' => 'Open Repair Order',
            'deep_link' => MobileCompanionDeepLink::repairOrder((int) $repairOrder->repair_order_id),
            'priority' => 5,
            'default_expanded' => false,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     */
    private function applyDefaultExpansion(array &$cards): void
    {
        if ($cards === []) {
            return;
        }

        usort($cards, fn (array $a, array $b): int => ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99));

        $expandOrder = ['pickup', 'estimate', 'parts', 'repair_order', 'inspection', 'vehicle', 'appointment', 'warranty'];
        $keys = collect($cards)->pluck('key')->all();
        $expandKey = collect($expandOrder)->first(fn (string $key): bool => in_array($key, $keys, true));

        foreach ($cards as &$card) {
            $card['default_expanded'] = ($card['key'] ?? '') === $expandKey;
        }
        unset($card);
    }
}
