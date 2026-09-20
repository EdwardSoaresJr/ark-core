<?php

namespace App\Ark\Operations\Appointments;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Http\Request;

/**
 * Every schedule entry point produces the same ScheduleContext.
 *
 * Conversation is an entry point only — never schedule authority.
 * Authority anchors (repair_order / vehicle / customer) win over conversation.
 */
final class ScheduleContextResolver
{
    public function fromRequest(Request $request): ScheduleContext
    {
        $repairOrderId = $this->queryInt($request, 'repair_order', 'repair_order_id');
        if ($repairOrderId !== null) {
            return $this->fromRepairOrder($repairOrderId);
        }

        $vehicleId = $this->queryInt($request, 'vehicle', 'vehicle_id');
        $customerId = $this->queryInt($request, 'customer', 'customer_id');

        if ($vehicleId !== null) {
            return $this->fromVehicle($vehicleId, $customerId);
        }

        if ($customerId !== null) {
            return $this->fromCustomer($customerId);
        }

        $leadId = $this->queryInt($request, 'lead', 'lead_id');
        if ($leadId !== null) {
            return $this->fromLead($leadId);
        }

        $conversationId = $this->queryInt($request, 'conversation', 'conversation_id');
        if ($conversationId !== null) {
            return $this->fromConversation($conversationId);
        }

        return new ScheduleContext(entry: 'blank');
    }

    public function fromRepairOrder(int $repairOrderId): ScheduleContext
    {
        $repairOrder = RepairOrder::query()
            ->with(['customer.vehicles', 'vehicle'])
            ->findOrFail($repairOrderId);

        return new ScheduleContext(
            customerId: $repairOrder->customer_id !== null ? (int) $repairOrder->customer_id : null,
            vehicleId: $repairOrder->vehicle_id !== null ? (int) $repairOrder->vehicle_id : null,
            repairOrderId: (int) $repairOrder->id,
            defaultConcern: $this->concernFromRepairOrder($repairOrder),
            entry: 'repair_order',
        );
    }

    public function fromVehicle(int $vehicleId, ?int $expectedCustomerId = null): ScheduleContext
    {
        $vehicle = Vehicle::query()->with('customer.vehicles')->findOrFail($vehicleId);

        if ($expectedCustomerId !== null && (int) $vehicle->customer_id !== $expectedCustomerId) {
            abort(404);
        }

        $activeRepairOrder = $this->activeRepairOrderForVehicle((int) $vehicle->id);

        return new ScheduleContext(
            customerId: (int) $vehicle->customer_id,
            vehicleId: (int) $vehicle->id,
            repairOrderId: $activeRepairOrder?->id,
            defaultConcern: $this->concernFromRepairOrder($activeRepairOrder),
            entry: 'vehicle',
        );
    }

    public function fromCustomer(int $customerId, ?int $vehicleId = null): ScheduleContext
    {
        $customer = Customer::query()->with('vehicles')->findOrFail($customerId);

        $selectedVehicleId = $vehicleId;
        if ($selectedVehicleId !== null) {
            $ownsVehicle = $customer->vehicles->contains(
                static fn (Vehicle $vehicle): bool => (int) $vehicle->id === $selectedVehicleId,
            );
            if (! $ownsVehicle) {
                abort(404);
            }
        } elseif ($customer->vehicles->count() === 1) {
            $selectedVehicleId = (int) $customer->vehicles->first()->id;
        }

        $activeRepairOrder = $selectedVehicleId !== null
            ? $this->activeRepairOrderForVehicle($selectedVehicleId)
            : null;

        return new ScheduleContext(
            customerId: (int) $customer->id,
            vehicleId: $selectedVehicleId,
            repairOrderId: $activeRepairOrder?->id,
            defaultConcern: $this->concernFromRepairOrder($activeRepairOrder),
            entry: 'customer',
        );
    }

    public function fromLead(int $leadId): ScheduleContext
    {
        $lead = Lead::query()->findOrFail($leadId);

        if ($lead->customer_id !== null) {
            $context = $this->fromCustomer((int) $lead->customer_id, $lead->vehicle_id !== null ? (int) $lead->vehicle_id : null);

            return new ScheduleContext(
                customerId: $context->customerId,
                vehicleId: $context->vehicleId,
                repairOrderId: $lead->repair_order_id !== null ? (int) $lead->repair_order_id : $context->repairOrderId,
                conversationId: $lead->conversation_id !== null ? (int) $lead->conversation_id : $context->conversationId,
                leadId: (int) $lead->id,
                defaultConcern: $this->concernFromLead($lead) ?? $context->defaultConcern,
                contactName: $this->contactNameFromLead($lead),
                contactPhone: $this->contactPhoneFromLead($lead),
                contactEmail: $this->contactEmailFromLead($lead),
                vehicleContextLabel: $this->vehicleLabelFromLead($lead),
                entry: 'lead',
            );
        }

        return new ScheduleContext(
            conversationId: $lead->conversation_id !== null ? (int) $lead->conversation_id : null,
            leadId: (int) $lead->id,
            defaultConcern: $this->concernFromLead($lead),
            contactName: $this->contactNameFromLead($lead),
            contactPhone: $this->contactPhoneFromLead($lead),
            contactEmail: $this->contactEmailFromLead($lead),
            vehicleContextLabel: $this->vehicleLabelFromLead($lead),
            needsCustomerIdentification: false,
            entry: 'lead',
        );
    }

    public function fromConversation(int $conversationId): ScheduleContext
    {
        $conversation = Conversation::query()->findOrFail($conversationId);
        $customerId = $this->linkedCustomerId($conversation);
        $lead = Lead::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->first();

        if ($customerId === null) {
            $phone = PhoneNumber::normalize((string) $conversation->contact_address)
                ?? trim((string) $conversation->contact_address);

            return new ScheduleContext(
                conversationId: (int) $conversation->id,
                leadId: $lead?->id,
                defaultConcern: $this->concernFromLead($lead),
                contactName: $this->contactNameFromLead($lead),
                contactPhone: $this->contactPhoneFromLead($lead) ?? ($phone !== '' ? $phone : null),
                contactEmail: $this->contactEmailFromLead($lead),
                vehicleContextLabel: $this->vehicleLabelFromLead($lead),
                needsCustomerIdentification: false,
                entry: 'conversation',
            );
        }

        $customer = Customer::query()->with('vehicles')->findOrFail($customerId);
        $linkedRepairOrder = $this->linkedRepairOrder($conversation);

        if ($linkedRepairOrder !== null && ! $linkedRepairOrder->status->isTerminal()) {
            $vehicleId = $linkedRepairOrder->vehicle_id !== null
                ? (int) $linkedRepairOrder->vehicle_id
                : ($customer->vehicles->count() === 1 ? (int) $customer->vehicles->first()->id : null);

            return new ScheduleContext(
                customerId: (int) $customer->id,
                vehicleId: $vehicleId,
                repairOrderId: (int) $linkedRepairOrder->id,
                conversationId: (int) $conversation->id,
                leadId: $lead?->id,
                defaultConcern: $this->concernFromRepairOrder($linkedRepairOrder) ?? $this->concernFromLead($lead),
                contactName: $this->contactNameFromLead($lead),
                contactPhone: $this->contactPhoneFromLead($lead),
                contactEmail: $this->contactEmailFromLead($lead),
                vehicleContextLabel: $this->vehicleLabelFromLead($lead),
                entry: 'conversation',
            );
        }

        $vehicleId = $customer->vehicles->count() === 1
            ? (int) $customer->vehicles->first()->id
            : null;

        $activeRepairOrder = $vehicleId !== null
            ? $this->activeRepairOrderForVehicle($vehicleId)
            : null;

        return new ScheduleContext(
            customerId: (int) $customer->id,
            vehicleId: $vehicleId,
            repairOrderId: $activeRepairOrder?->id,
            conversationId: (int) $conversation->id,
            leadId: $lead?->id,
            defaultConcern: $this->concernFromRepairOrder($activeRepairOrder) ?? $this->concernFromLead($lead),
            contactName: $this->contactNameFromLead($lead),
            contactPhone: $this->contactPhoneFromLead($lead),
            contactEmail: $this->contactEmailFromLead($lead),
            vehicleContextLabel: $this->vehicleLabelFromLead($lead),
            entry: 'conversation',
        );
    }

    private function concernFromLead(?Lead $lead): ?string
    {
        if ($lead === null) {
            return null;
        }

        $concern = trim((string) ($lead->concern ?? ''));

        return $concern !== '' ? $concern : null;
    }

    private function contactNameFromLead(?Lead $lead): ?string
    {
        if ($lead === null) {
            return null;
        }

        $name = trim((string) ($lead->contact_name ?? ''));

        return $name !== '' ? $name : null;
    }

    private function contactPhoneFromLead(?Lead $lead): ?string
    {
        if ($lead === null) {
            return null;
        }

        $phone = PhoneNumber::normalize((string) ($lead->contact_phone ?? ''))
            ?? trim((string) ($lead->contact_phone ?? ''));

        return $phone !== '' ? $phone : null;
    }

    private function contactEmailFromLead(?Lead $lead): ?string
    {
        if ($lead === null) {
            return null;
        }

        $email = strtolower(trim((string) ($lead->contact_email ?? '')));

        return $email !== '' ? $email : null;
    }

    private function vehicleLabelFromLead(?Lead $lead): ?string
    {
        if ($lead === null) {
            return null;
        }

        $label = trim(collect([
            $lead->vehicle_year,
            $lead->vehicle_make,
            $lead->vehicle_model,
        ])->filter()->implode(' '));

        return $label !== '' ? $label : null;
    }

    private function linkedCustomerId(Conversation $conversation): ?int
    {
        $link = ConversationLink::query()
            ->where('conversation_id', $conversation->id)
            ->where('linkable_type', (new Customer)->getMorphClass())
            ->first();

        return $link !== null ? (int) $link->linkable_id : null;
    }

    private function linkedRepairOrder(Conversation $conversation): ?RepairOrder
    {
        $conversation->loadMissing('links.linkable');

        foreach ($conversation->links as $link) {
            if ($link->linkable instanceof RepairOrder) {
                return $link->linkable;
            }
        }

        return null;
    }

    private function activeRepairOrderForVehicle(int $vehicleId): ?RepairOrder
    {
        $activeStatuses = RepairOrderStatus::operationalQueueValues();

        return RepairOrder::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', $activeStatuses)
            ->orderByDesc('updated_at')
            ->first();
    }

    private function concernFromRepairOrder(?RepairOrder $repairOrder): ?string
    {
        if ($repairOrder === null) {
            return null;
        }

        $summary = trim((string) ($repairOrder->concern_summary ?? ''));

        return $summary !== ''
            ? $summary
            : 'Advisor follow-up · RO #'.$repairOrder->repair_order_id;
    }

    private function queryInt(Request $request, string $friendly, string $legacy): ?int
    {
        if ($request->filled($friendly)) {
            $value = $request->integer($friendly);

            return $value > 0 ? $value : null;
        }

        if ($request->filled($legacy)) {
            $value = $request->integer($legacy);

            return $value > 0 ? $value : null;
        }

        return null;
    }
}
