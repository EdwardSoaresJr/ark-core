<?php

namespace App\Ark\Operations\Conversations\Projections;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderVisitMode;
use Illuminate\Support\Carbon;

/**
 * Persistent customer orientation for conversation surfaces.
 */
final class ConversationContextProjection
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forConversation(Conversation $conversation, ?Carbon $lastActivityAt = null): array
    {
        $conversation->loadMissing(['owner:id,name']);

        $phone = $conversation->contact_surface === ConversationContactSurface::Phone
            ? trim((string) $conversation->contact_address)
            : null;

        $callContext = $phone !== '' && $phone !== null
            ? $this->callContextResolver->resolve($phone)
            : null;

        $customer = $callContext?->customer;
        $primaryRo = $callContext?->openRepairOrders->first()?->repairOrder
            ?? $this->primaryRepairOrderFromLinks($conversation);

        if ($primaryRo instanceof RepairOrder) {
            $primaryRo->loadMissing(['vehicle', 'customer']);
            $customer ??= $primaryRo->customer;
        }

        return [
            'customer' => $customer instanceof Customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => PhoneNumber::display($customer->phone) ?? $customer->phone,
            ] : [
                'id' => null,
                'name' => $phone !== null && $phone !== ''
                    ? (PhoneNumber::display($phone) ?? $phone)
                    : 'Unknown contact',
                'phone' => $phone !== null && $phone !== '' ? PhoneNumber::display($phone) ?? $phone : null,
            ],
            'vehicle' => $this->vehicleContext($primaryRo),
            'repair_order' => $this->repairOrderContext($primaryRo),
            'conversation' => [
                'waiting_on_label' => $conversation->waiting_on?->label(),
                'status_label' => $conversation->status->label(),
                'assigned_to' => $conversation->owner?->name,
            ],
            'last_activity' => $this->lastActivityContext($lastActivityAt),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function vehicleContext(?RepairOrder $repairOrder): ?array
    {
        if ($repairOrder === null) {
            return null;
        }

        $vehicle = $repairOrder->vehicle;

        if ($vehicle === null) {
            return null;
        }

        return [
            'id' => $vehicle->id,
            'label' => trim("{$vehicle->year} {$vehicle->make} {$vehicle->model}"),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function repairOrderContext(?RepairOrder $repairOrder): ?array
    {
        if ($repairOrder === null) {
            return null;
        }

        return [
            'id' => $repairOrder->id,
            'number' => $repairOrder->repair_order_id,
            'number_label' => '#'.$repairOrder->repair_order_id,
            'lifecycle_label' => $repairOrder->statusDisplayLabel(),
            'vehicle_location_label' => $this->vehicleLocationLabel($repairOrder),
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function lastActivityContext(?Carbon $lastActivityAt): ?array
    {
        if ($lastActivityAt === null) {
            return null;
        }

        return [
            'occurred_at' => $lastActivityAt->toIso8601String(),
            'label' => $lastActivityAt->diffForHumans(short: true),
        ];
    }

    private function vehicleLocationLabel(RepairOrder $repairOrder): ?string
    {
        $visitMode = RepairOrderVisitMode::fromRepairOrder($repairOrder);

        return match ($visitMode) {
            RepairOrderVisitMode::WaitingHere => 'Vehicle in shop',
            RepairOrderVisitMode::DropOff => 'Drop off',
            RepairOrderVisitMode::NeedsShuttle => 'Needs shuttle',
            RepairOrderVisitMode::TowIncoming => 'Tow incoming',
            default => null,
        };
    }

    private function primaryRepairOrderFromLinks(Conversation $conversation): ?RepairOrder
    {
        $conversation->loadMissing('links.linkable');

        foreach ($conversation->links as $link) {
            if ($link->linkable instanceof RepairOrder) {
                return $link->linkable;
            }
        }

        return null;
    }
}
