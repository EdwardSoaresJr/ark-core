<?php

namespace App\Ark\Growth\Identity;

use App\Ark\Growth\Models\GrowthSession;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Telephony\CallSession;

final class IdentityConfidenceResolver
{
    public function resolve(?GrowthSession $session, ?RepairOrder $repairOrder = null): IdentityConfidence
    {
        if ($session === null) {
            return IdentityConfidence::anonymous();
        }

        $repairOrder ??= $session->repairOrders()->latest('id')->first();

        if ($repairOrder !== null && $repairOrder->customer_id !== null) {
            $portalAuth = $this->portalAuthenticationEvidence($session, $repairOrder);

            if ($portalAuth !== null) {
                return $portalAuth;
            }
        }

        $lead = $this->linkedLead($session, $repairOrder);

        if ($lead !== null) {
            return $this->fromLead($lead, $session, $repairOrder);
        }

        $conversation = $this->linkedConversation($session, $repairOrder);

        if ($conversation !== null && $conversation->customer_id !== null) {
            return new IdentityConfidence(
                score: 88,
                reason: 'Matched conversation to customer relationship.',
                signals: [
                    ['label' => 'Conversation linked to customer', 'satisfied' => true],
                    ['label' => 'Same Growth session', 'satisfied' => true],
                ],
                facts: $this->factRows(array_filter([
                    'Conversation' => '#'.$conversation->id,
                    'Customer' => '#'.$conversation->customer_id,
                    'Session' => '#'.$session->id,
                ])),
                evidence: array_filter([
                    'conversation_id' => $conversation->id,
                    'customer_id' => $conversation->customer_id,
                    'growth_session_id' => $session->id,
                ]),
            );
        }

        $callMatch = $this->phoneCallMatch($session, $repairOrder);

        if ($callMatch !== null) {
            return $callMatch;
        }

        if ($session->leads()->exists() || $session->repairOrders()->exists()) {
            return new IdentityConfidence(
                score: 61,
                reason: 'Session linked to shop activity before customer identity was confirmed.',
                signals: [
                    ['label' => 'Growth session linked to shop activity', 'satisfied' => true],
                    ['label' => 'Customer identity confirmed', 'satisfied' => false],
                ],
                facts: $this->factRows([
                    'Session' => '#'.$session->id,
                    'Visitor' => $session->visitor_id,
                ]),
                evidence: [
                    'growth_session_id' => $session->id,
                    'visitor_id' => $session->visitor_id,
                ],
            );
        }

        return IdentityConfidence::anonymous();
    }

    public function persist(GrowthSession $session, IdentityConfidence $confidence): void
    {
        $session->forceFill([
            'identity_confidence_score' => min(100, max(0, $confidence->score)),
            'identity_confidence_reason' => $confidence->reason,
            'identity_confidence_evidence' => $confidence->evidence !== [] ? $confidence->evidence : null,
        ])->save();
    }

    public function resolveAndPersist(?GrowthSession $session, ?RepairOrder $repairOrder = null): IdentityConfidence
    {
        $confidence = $this->resolve($session, $repairOrder);

        if ($session !== null) {
            $this->persist($session, $confidence);
        }

        return $confidence;
    }

    private function portalAuthenticationEvidence(GrowthSession $session, RepairOrder $repairOrder): ?IdentityConfidence
    {
        $metadata = is_array($session->metadata) ? $session->metadata : [];
        $portalCustomerId = (int) ($metadata['portal_customer_id'] ?? 0);

        if ($portalCustomerId > 0 && $portalCustomerId === (int) $repairOrder->customer_id) {
            return new IdentityConfidence(
                score: 100,
                reason: 'Portal authentication',
                signals: [
                    ['label' => 'Portal authentication', 'satisfied' => true],
                    ['label' => 'Customer matches repair order', 'satisfied' => true],
                ],
                facts: $this->factRows([
                    'Customer' => '#'.$portalCustomerId,
                    'Repair order' => '#'.$repairOrder->repair_order_id,
                    'Session' => '#'.$session->id,
                ]),
                evidence: [
                    'customer_id' => $portalCustomerId,
                    'growth_session_id' => $session->id,
                    'repair_order_id' => $repairOrder->repair_order_id,
                ],
            );
        }

        return null;
    }

    private function linkedLead(GrowthSession $session, ?RepairOrder $repairOrder): ?Lead
    {
        if ($repairOrder !== null) {
            $lead = Lead::query()
                ->where('growth_session_id', $session->id)
                ->where('repair_order_id', $repairOrder->repair_order_id)
                ->latest('id')
                ->first();

            if ($lead !== null) {
                return $lead;
            }
        }

        return $session->leads()->latest('id')->first();
    }

    private function linkedConversation(GrowthSession $session, ?RepairOrder $repairOrder): ?Conversation
    {
        if ($repairOrder !== null) {
            $conversation = Conversation::query()
                ->where('growth_session_id', $session->id)
                ->where('customer_id', $repairOrder->customer_id)
                ->latest('id')
                ->first();

            if ($conversation !== null) {
                return $conversation;
            }
        }

        return $session->conversations()->latest('id')->first();
    }

    private function fromLead(Lead $lead, GrowthSession $session, ?RepairOrder $repairOrder): IdentityConfidence
    {
        $phone = trim((string) ($lead->contact_phone ?? ''));
        $email = trim((string) ($lead->contact_email ?? ''));

        if ($phone !== '') {
            return new IdentityConfidence(
                score: 95,
                reason: 'Matched incoming phone number',
                signals: [
                    ['label' => 'Phone matched lead', 'satisfied' => true],
                    ['label' => 'Same browser session', 'satisfied' => true],
                    ['label' => 'Same landing page', 'satisfied' => filled($session->first_landing_page)],
                ],
                facts: $this->factRows(array_filter([
                    'Phone' => $phone,
                    'Lead' => '#'.$lead->id,
                    'Conversation' => $lead->conversation_id ? '#'.$lead->conversation_id : null,
                    'Customer' => $lead->customer_id ? '#'.$lead->customer_id : null,
                    'Landing page' => $session->first_landing_page,
                ])),
                evidence: array_filter([
                    'phone' => $phone,
                    'lead_id' => $lead->id,
                    'conversation_id' => $lead->conversation_id,
                    'customer_id' => $lead->customer_id,
                    'growth_session_id' => $session->id,
                    'repair_order_id' => $repairOrder?->repair_order_id,
                ]),
            );
        }

        if ($email !== '') {
            return new IdentityConfidence(
                score: 82,
                reason: 'Matched lead email address',
                signals: [
                    ['label' => 'Email matched lead', 'satisfied' => true],
                    ['label' => 'Same browser session', 'satisfied' => true],
                ],
                facts: $this->factRows(array_filter([
                    'Email' => $email,
                    'Lead' => '#'.$lead->id,
                    'Customer' => $lead->customer_id ? '#'.$lead->customer_id : null,
                ])),
                evidence: array_filter([
                    'email' => $email,
                    'lead_id' => $lead->id,
                    'customer_id' => $lead->customer_id,
                    'growth_session_id' => $session->id,
                    'repair_order_id' => $repairOrder?->repair_order_id,
                ]),
            );
        }

        return new IdentityConfidence(
            score: 70,
            reason: 'Lead submitted without direct identity match',
            signals: [
                ['label' => 'Lead linked to session', 'satisfied' => true],
                ['label' => 'Phone or email match', 'satisfied' => false],
            ],
            facts: $this->factRows([
                'Lead' => '#'.$lead->id,
                'Session' => '#'.$session->id,
            ]),
            evidence: array_filter([
                'lead_id' => $lead->id,
                'growth_session_id' => $session->id,
                'repair_order_id' => $repairOrder?->repair_order_id,
            ]),
        );
    }

    /**
     * @param  array<string, string|null>  $rows
     * @return list<array{label: string, value: string}>
     */
    private function factRows(array $rows): array
    {
        $facts = [];

        foreach ($rows as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $facts[] = ['label' => $label, 'value' => $value];
        }

        return $facts;
    }

    private function phoneCallMatch(GrowthSession $session, ?RepairOrder $repairOrder): ?IdentityConfidence
    {
        $customerId = $repairOrder?->customer_id;

        if ($customerId === null) {
            return null;
        }

        $customer = Customer::query()->find($customerId);

        if ($customer === null) {
            return null;
        }

        $normalizedPhone = PhoneNumber::normalize($customer->getAttributes()['phone'] ?? null) ?? '';

        if ($normalizedPhone === '') {
            return null;
        }

        $call = CallSession::query()
            ->where('customer_id', $customerId)
            ->where('normalized_from', $normalizedPhone)
            ->where('started_at', '>=', $session->started_at)
            ->orderBy('started_at')
            ->first();

        if ($call === null) {
            return null;
        }

        return new IdentityConfidence(
            score: 78,
            reason: 'Cookie session matched to inbound call from customer phone',
            signals: [
                ['label' => 'Inbound call from customer phone', 'satisfied' => true],
                ['label' => 'Same browser session', 'satisfied' => true],
            ],
            facts: $this->factRows([
                'Phone' => $customer->phone ?? $normalizedPhone,
                'Call' => '#'.$call->id,
                'Customer' => '#'.$customerId,
                'Session' => '#'.$session->id,
            ]),
            evidence: [
                'phone' => $customer->phone ?? $normalizedPhone,
                'call_session_id' => $call->id,
                'customer_id' => $customerId,
                'growth_session_id' => $session->id,
            ],
        );
    }
}
