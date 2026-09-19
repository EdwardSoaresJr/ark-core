<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class CreateRecommendationAction
{
    /**
     * @param  array{
     *     customer: Customer,
     *     vehicle: Vehicle,
     *     title: string,
     *     customer_description?: ?string,
     *     advisor_note?: ?string,
     *     originating_repair_order?: ?RepairOrder,
     *     originating_inspection?: ?Inspection,
     *     originating_inspection_item?: ?InspectionItem,
     *     originating_concern?: ?RepairOrderConcern,
     *     discovered_at?: Carbon|string|null,
     *     discovered_mileage?: ?int,
     *     urgency?: RecommendationUrgency|string|null,
     *     safety_related?: bool,
     *     due_kind?: RecommendationDueKind|string|null,
     *     due_on?: Carbon|string|null,
     *     due_mileage?: ?int,
     *     follow_up_at?: Carbon|string|null,
     *     follow_up_owner_user_id?: ?int,
     *     source_kind?: RecommendationSourceKind|string|null,
     * }  $input
     */
    public function handle(array $input): Recommendation
    {
        $title = trim((string) ($input['title'] ?? ''));

        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'A recommendation needs a title.',
            ]);
        }

        $customer = $input['customer'];
        $vehicle = $input['vehicle'];

        if ((int) $vehicle->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'That vehicle does not belong to this customer.',
            ]);
        }

        $repairOrder = $input['originating_repair_order'] ?? null;
        $inspection = $input['originating_inspection'] ?? null;
        $item = $input['originating_inspection_item'] ?? null;
        $concern = $input['originating_concern'] ?? null;

        if ($repairOrder instanceof RepairOrder) {
            $this->assertSameCustomerVehicle($repairOrder->customer_id, $repairOrder->vehicle_id, $customer, $vehicle);
        }

        if ($concern instanceof RepairOrderConcern) {
            $concern->loadMissing('repairOrder');
            $this->assertSameCustomerVehicle(
                $concern->repairOrder?->customer_id,
                $concern->repairOrder?->vehicle_id,
                $customer,
                $vehicle,
            );
        }

        if ($item instanceof InspectionItem) {
            $item->loadMissing('inspection.repairOrder');
            $inspection ??= $item->inspection;
        }

        if ($inspection instanceof Inspection) {
            $inspection->loadMissing('repairOrder');
            $this->assertSameCustomerVehicle(
                $inspection->repairOrder?->customer_id,
                $inspection->repairOrder?->vehicle_id,
                $customer,
                $vehicle,
            );
        }

        $dueKind = $this->dueKind($input['due_kind'] ?? null);
        $urgency = $this->urgency($input['urgency'] ?? null);
        $source = $this->sourceKind($input['source_kind'] ?? null, $item, $concern);

        return Recommendation::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'originating_repair_order_id' => $repairOrder?->id ?? $inspection?->repair_order_id ?? $concern?->repair_order_id,
            'originating_inspection_id' => $inspection?->id ?? $item?->inspection_id,
            'originating_inspection_item_id' => $item?->id,
            'originating_repair_order_concern_id' => $concern?->id ?? $item?->repair_order_concern_id,
            'title' => $title,
            'customer_description' => $this->nullableString($input['customer_description'] ?? null),
            'advisor_note' => $this->nullableString($input['advisor_note'] ?? null),
            'lifecycle' => RecommendationLifecycle::Open,
            'discovered_at' => $input['discovered_at'] ?? now(),
            'discovered_mileage' => $this->nullableInt($input['discovered_mileage'] ?? null)
                ?? $repairOrder?->resolvedMileageIn(),
            'urgency' => $urgency,
            'safety_related' => (bool) ($input['safety_related'] ?? false),
            'due_kind' => $dueKind,
            'due_on' => $input['due_on'] ?? null,
            'due_mileage' => $this->nullableInt($input['due_mileage'] ?? null),
            'follow_up_at' => $input['follow_up_at'] ?? null,
            'follow_up_owner_user_id' => $input['follow_up_owner_user_id'] ?? null,
            'source_kind' => $source,
        ]);
    }

    private function assertSameCustomerVehicle(?int $customerId, ?int $vehicleId, Customer $customer, Vehicle $vehicle): void
    {
        if ($customerId !== null && (int) $customerId !== (int) $customer->id) {
            throw ValidationException::withMessages([
                'customer_id' => 'Recommendation source does not belong to this customer.',
            ]);
        }

        if ($vehicleId !== null && (int) $vehicleId !== (int) $vehicle->id) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'Recommendation source does not belong to this vehicle.',
            ]);
        }
    }

    private function dueKind(RecommendationDueKind|string|null $value): RecommendationDueKind
    {
        if ($value instanceof RecommendationDueKind) {
            return $value;
        }

        return RecommendationDueKind::tryFrom((string) $value) ?? RecommendationDueKind::None;
    }

    private function urgency(RecommendationUrgency|string|null $value): RecommendationUrgency
    {
        if ($value instanceof RecommendationUrgency) {
            return $value;
        }

        return RecommendationUrgency::tryFrom((string) $value) ?? RecommendationUrgency::Soon;
    }

    private function sourceKind(
        RecommendationSourceKind|string|null $value,
        ?InspectionItem $item,
        ?RepairOrderConcern $concern,
    ): RecommendationSourceKind {
        if ($value instanceof RecommendationSourceKind) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return RecommendationSourceKind::tryFrom($value) ?? RecommendationSourceKind::Advisor;
        }

        if ($item instanceof InspectionItem) {
            return RecommendationSourceKind::Inspection;
        }

        if ($concern instanceof RepairOrderConcern) {
            return RecommendationSourceKind::Estimate;
        }

        return RecommendationSourceKind::Advisor;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
