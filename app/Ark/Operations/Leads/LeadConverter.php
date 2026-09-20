<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;

/**
 * Lead becomes converted when reality changes — not when an advisor remembers a button.
 */
class LeadConverter
{
    private const int MULTI_LEAD_MIN_SCORE = 5;

    public function convertFromRepairOrder(RepairOrder $repairOrder, ?int $leadId, ?User $actor = null): ?Lead
    {
        $lead = $this->resolveLead($repairOrder, $leadId);

        if ($lead === null || $lead->state === LeadState::Converted) {
            return $lead;
        }

        if ($lead->state === LeadState::Lost) {
            return $lead;
        }

        $now = now();

        $lead->fill([
            'state' => LeadState::Converted,
            'customer_id' => $repairOrder->customer_id,
            'vehicle_id' => $repairOrder->vehicle_id,
            'repair_order_id' => $repairOrder->id,
            'converted_at' => $lead->converted_at ?? $now,
        ]);

        if ($lead->first_contacted_at === null) {
            $lead->first_contacted_at = $now;
        }

        if ($actor !== null && $lead->assigned_user_id === null) {
            $lead->assigned_user_id = $actor->id;
        }

        $lead->save();

        $this->syncCustomerContactFromLead($lead, $repairOrder);

        return $lead->fresh();
    }

    private function syncCustomerContactFromLead(Lead $lead, RepairOrder $repairOrder): void
    {
        $customer = $repairOrder->customer;

        if ($customer === null) {
            return;
        }

        $updates = [];

        if ($customer->contact_preference === null && $lead->contact_preference !== null) {
            $updates['contact_preference'] = $lead->contact_preference;
        }

        if (! filled($customer->email) && filled($lead->contact_email)) {
            $updates['email'] = trim((string) $lead->contact_email);
        }

        if ($updates !== []) {
            $customer->update($updates);
        }
    }

    public function findOpenLeadForRepairOrder(RepairOrder $repairOrder): ?Lead
    {
        $repairOrder->loadMissing(['customer', 'vehicle', 'concerns']);

        $customer = $repairOrder->customer;

        if ($customer === null) {
            return null;
        }

        $phone = PhoneNumber::normalize($customer->phone);

        if ($phone === null) {
            return null;
        }

        $candidates = Lead::query()
            ->open()
            ->whereNull('repair_order_id')
            ->where('contact_phone', $phone)
            ->orderByDesc('id')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        $best = null;
        $bestScore = 0;

        foreach ($candidates as $candidate) {
            $score = $this->matchScore($candidate, $repairOrder);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $bestScore >= self::MULTI_LEAD_MIN_SCORE ? $best : null;
    }

    public function findRepairOrderForOpenLead(Lead $lead): ?RepairOrder
    {
        if (! $lead->isOpen() || $lead->repair_order_id !== null) {
            return null;
        }

        $phone = PhoneNumber::normalize($lead->contact_phone);

        if ($phone === null) {
            return null;
        }

        $candidates = RepairOrder::query()
            ->with(['customer', 'vehicle', 'concerns'])
            ->whereHas('customer', fn ($query) => $query->where('phone', $phone))
            ->where('status', '!=', RepairOrderStatus::Closed->value)
            ->orderByDesc('id')
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() === 1) {
            $repairOrder = $candidates->first();

            return $this->matchScore($lead, $repairOrder) >= 1 ? $repairOrder : null;
        }

        $best = null;
        $bestScore = 0;

        foreach ($candidates as $candidate) {
            $score = $this->matchScore($lead, $candidate);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $bestScore >= self::MULTI_LEAD_MIN_SCORE ? $best : null;
    }

    /**
     * @return list<array{lead_id: int, shop_repair_order_id: int|null, contact_name: ?string}>
     */
    public function reconcileOpenLeads(?User $actor = null): array
    {
        $linked = [];

        Lead::query()
            ->open()
            ->whereNull('repair_order_id')
            ->orderBy('id')
            ->each(function (Lead $lead) use (&$linked, $actor): void {
                $repairOrder = $this->findRepairOrderForOpenLead($lead);

                if ($repairOrder === null) {
                    return;
                }

                if (Lead::query()->where('repair_order_id', $repairOrder->id)->exists()) {
                    return;
                }

                $this->convertFromRepairOrder($repairOrder, $lead->id, $actor);

                $linked[] = [
                    'lead_id' => $lead->id,
                    'shop_repair_order_id' => $repairOrder->repair_order_id,
                    'contact_name' => $lead->contact_name,
                ];
            });

        return $linked;
    }

    private function resolveLead(RepairOrder $repairOrder, ?int $leadId): ?Lead
    {
        if ($leadId !== null && $leadId > 0) {
            return Lead::query()->find($leadId);
        }

        return $this->findOpenLeadForRepairOrder($repairOrder);
    }

    private function matchScore(Lead $lead, RepairOrder $repairOrder): int
    {
        $score = 1;

        if ($lead->customer_id !== null && $lead->customer_id === $repairOrder->customer_id) {
            $score += 10;
        }

        if ($lead->vehicle_id !== null && $lead->vehicle_id === $repairOrder->vehicle_id) {
            $score += 10;
        }

        $vehicle = $repairOrder->vehicle;

        if ($vehicle !== null && $this->leadVehicleMatches($lead, $vehicle)) {
            $score += 8;
        }

        if ($this->leadConcernMatches($lead, $repairOrder)) {
            $score += 5;
        }

        return $score;
    }

    private function leadVehicleMatches(Lead $lead, Vehicle $vehicle): bool
    {
        if (! filled($lead->vehicle_year) && ! filled($lead->vehicle_make) && ! filled($lead->vehicle_model)) {
            return false;
        }

        if ($lead->vehicle_year !== null && (int) $lead->vehicle_year !== (int) $vehicle->year) {
            return false;
        }

        if (filled($lead->vehicle_make) && ! $this->stringsMatch($lead->vehicle_make, $vehicle->make)) {
            return false;
        }

        if (filled($lead->vehicle_model) && ! $this->stringsMatch($lead->vehicle_model, $vehicle->model)) {
            return false;
        }

        return true;
    }

    private function leadConcernMatches(Lead $lead, RepairOrder $repairOrder): bool
    {
        $leadConcern = mb_strtolower(trim((string) $lead->concern));

        if ($leadConcern === '') {
            return false;
        }

        $repairOrderTexts = collect([$repairOrder->concern_summary])
            ->merge($repairOrder->concerns->pluck('customer_states'))
            ->map(fn ($text): string => mb_strtolower(trim((string) $text)))
            ->filter()
            ->all();

        foreach ($repairOrderTexts as $text) {
            if ($text === $leadConcern) {
                return true;
            }

            if (str_contains($text, $leadConcern) || str_contains($leadConcern, $text)) {
                return true;
            }
        }

        return false;
    }

    private function stringsMatch(?string $left, ?string $right): bool
    {
        return mb_strtolower(trim((string) $left)) === mb_strtolower(trim((string) $right));
    }
}
