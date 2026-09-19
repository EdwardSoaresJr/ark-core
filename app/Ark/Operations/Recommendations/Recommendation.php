<?php

namespace App\Ark\Operations\Recommendations;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\Inspections\InspectionItem;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'customer_id',
    'vehicle_id',
    'originating_repair_order_id',
    'originating_inspection_id',
    'originating_inspection_item_id',
    'originating_repair_order_concern_id',
    'title',
    'customer_description',
    'advisor_note',
    'lifecycle',
    'discovered_at',
    'discovered_mileage',
    'urgency',
    'safety_related',
    'due_kind',
    'due_on',
    'due_mileage',
    'follow_up_at',
    'follow_up_owner_user_id',
    'follow_up_completed_at',
    'follow_up_snoozed_until',
    'resolved_at',
    'resolved_mileage',
    'resolved_reason',
    'resolved_note',
    'dismissed_at',
    'dismissal_reason',
    'dismissal_note',
    'source_kind',
])]
class Recommendation extends Model
{
    protected function casts(): array
    {
        return [
            'lifecycle' => RecommendationLifecycle::class,
            'urgency' => RecommendationUrgency::class,
            'due_kind' => RecommendationDueKind::class,
            'source_kind' => RecommendationSourceKind::class,
            'resolved_reason' => RecommendationResolvedReason::class,
            'safety_related' => 'boolean',
            'discovered_at' => 'datetime',
            'discovered_mileage' => 'integer',
            'due_on' => 'date',
            'due_mileage' => 'integer',
            'follow_up_at' => 'datetime',
            'follow_up_completed_at' => 'datetime',
            'follow_up_snoozed_until' => 'datetime',
            'resolved_at' => 'datetime',
            'resolved_mileage' => 'integer',
            'dismissed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function originatingRepairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class, 'originating_repair_order_id');
    }

    public function originatingInspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class, 'originating_inspection_id');
    }

    public function originatingInspectionItem(): BelongsTo
    {
        return $this->belongsTo(InspectionItem::class, 'originating_inspection_item_id');
    }

    public function originatingConcern(): BelongsTo
    {
        return $this->belongsTo(RepairOrderConcern::class, 'originating_repair_order_concern_id');
    }

    public function followUpOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follow_up_owner_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(RecommendationEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function estimateLinks(): HasMany
    {
        return $this->hasMany(RecommendationEstimateLink::class);
    }

    public function isOpen(): bool
    {
        return $this->lifecycle === RecommendationLifecycle::Open;
    }

    public function belongsToVehicle(Vehicle $vehicle): bool
    {
        return (int) $this->vehicle_id === (int) $vehicle->id;
    }

    public function belongsToCustomer(Customer $customer): bool
    {
        return (int) $this->customer_id === (int) $customer->id;
    }

    public function dueIsNow(): bool
    {
        return $this->due_kind === RecommendationDueKind::Now;
    }

    public function isServiceDue(?int $currentMileage = null, ?Carbon $asOf = null): bool
    {
        $asOf ??= now();

        return match ($this->due_kind) {
            RecommendationDueKind::Now => true,
            RecommendationDueKind::Date => $this->due_on !== null && $this->due_on->lte($asOf->copy()->startOfDay()),
            RecommendationDueKind::Mileage => $currentMileage !== null
                && $this->due_mileage !== null
                && $currentMileage >= (int) $this->due_mileage,
            RecommendationDueKind::DateOrMileage => ($this->due_on !== null && $this->due_on->lte($asOf->copy()->startOfDay()))
                || ($currentMileage !== null && $this->due_mileage !== null && $currentMileage >= (int) $this->due_mileage),
            RecommendationDueKind::None => false,
        };
    }

    public function followUpIsDue(?Carbon $asOf = null): bool
    {
        if ($this->follow_up_at === null || $this->follow_up_completed_at !== null) {
            return false;
        }

        $asOf ??= now();

        if ($this->follow_up_snoozed_until !== null && $this->follow_up_snoozed_until->gt($asOf)) {
            return false;
        }

        return $this->follow_up_at->lte($asOf);
    }

    public function lastPresentation(): ?RecommendationEvent
    {
        return $this->events
            ->where('type', RecommendationEventType::Presented)
            ->last();
    }

    public function lastDecision(): ?RecommendationEvent
    {
        return $this->events
            ->filter(fn (RecommendationEvent $event): bool => $event->type->isDecision())
            ->last();
    }

    public function wasPresented(): bool
    {
        return $this->events->contains(
            fn (RecommendationEvent $event): bool => $event->type === RecommendationEventType::Presented,
        );
    }

    public function wasDeclined(): bool
    {
        return $this->events->contains(
            fn (RecommendationEvent $event): bool => $event->type === RecommendationEventType::Declined,
        );
    }

    public function estimateLinkForRepairOrder(RepairOrder $repairOrder): ?RecommendationEstimateLink
    {
        return $this->estimateLinks->first(
            fn (RecommendationEstimateLink $link): bool => (int) $link->repair_order_id === (int) $repairOrder->id,
        );
    }

    public function isOnRepairOrder(RepairOrder $repairOrder): bool
    {
        return $this->estimateLinkForRepairOrder($repairOrder) !== null;
    }

    public function latestEstimatedAmountCents(): ?int
    {
        $event = $this->events
            ->reverse()
            ->first(fn (RecommendationEvent $event): bool => $event->amount_cents !== null);

        if ($event !== null) {
            return (int) $event->amount_cents;
        }

        $link = $this->estimateLinks->sortByDesc('id')->first();

        return $link?->amount_cents !== null ? (int) $link->amount_cents : null;
    }
}
