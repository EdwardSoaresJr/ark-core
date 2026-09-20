<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use Illuminate\Support\Str;

/**
 * Reconcile unknown inbound contact into Lead Truth.
 *
 * Known customers already have relationship context — no Lead required.
 * Unknown contacts are Lead candidates regardless of channel.
 */
class LeadReconciler
{
    public function __construct(
        private readonly LeadConcernComparator $concerns,
        private readonly LeadDuplicateConsolidator $consolidator,
    ) {}

    /**
     * Reconcile or open a Lead from an inbound SMS when the sender is not a known customer.
     */
    public function reconcileInboundSms(ConversationMessage $message, ?Customer $customer = null): ?Lead
    {
        if ($customer instanceof Customer) {
            return null;
        }

        $message->loadMissing('conversation');

        $existing = $this->findByIngressMessageId($message->id);

        if ($existing instanceof Lead) {
            return $existing;
        }

        $phone = PhoneNumber::normalize((string) $message->conversation?->contact_address);

        if ($phone === null) {
            return null;
        }

        $concern = $this->concernFromMessage($message);
        $openMatch = $this->findOpenLeadForConcern($phone, $concern);

        if ($openMatch instanceof Lead) {
            return $this->reuseLead($openMatch);
        }

        $threadMatch = $this->findOpenLeadForThreadContinuation(
            $phone,
            $concern,
            (int) $message->conversation_id,
        );

        if ($threadMatch instanceof Lead) {
            return $this->reuseLead($threadMatch);
        }

        $conversationLead = Lead::query()
            ->open()
            ->where('conversation_id', $message->conversation_id)
            ->orderByRaw('CASE WHEN first_contacted_at IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->first();

        if ($conversationLead instanceof Lead) {
            if (! $this->concerns->isMateriallyDistinctConcern($conversationLead->concern, $concern)) {
                return $this->reuseLead($conversationLead);
            }
        }

        return Lead::query()->create([
            'source' => LeadSource::Sms,
            'state' => LeadState::Received,
            'concern' => $concern,
            'contact_phone' => $phone,
            'conversation_id' => $message->conversation_id,
            'metadata' => [
                'ingress_conversation_message_id' => $message->id,
                'ingress_channel' => 'sms',
            ],
        ]);
    }

    private function reuseLead(Lead $lead): Lead
    {
        $this->consolidator->consolidateAround($lead);

        return $lead;
    }

    private function findByIngressMessageId(int $messageId): ?Lead
    {
        return Lead::query()
            ->where('metadata->ingress_conversation_message_id', $messageId)
            ->first();
    }

    private function findOpenLeadForConcern(string $phone, string $concern): ?Lead
    {
        $concernKey = $this->concerns->concernKey($concern);

        return Lead::query()
            ->open()
            ->where('contact_phone', $phone)
            ->orderByDesc('created_at')
            ->get()
            ->first(fn (Lead $lead): bool => $this->concerns->concernKey($lead->concern) === $concernKey);
    }

    private function concernFromMessage(ConversationMessage $message): string
    {
        $body = trim((string) $message->body);

        if ($body === '') {
            return '(attachment)';
        }

        return Str::limit($body, 2000, '');
    }

    private function findOpenLeadForThreadContinuation(
        string $phone,
        string $concern,
        int $conversationId,
    ): ?Lead {
        if ($this->concerns->isAcknowledgmentMessage($concern)) {
            return Lead::query()
                ->open()
                ->where('contact_phone', $phone)
                ->orderByDesc('created_at')
                ->first();
        }

        $conversationLead = Lead::query()
            ->open()
            ->where('conversation_id', $conversationId)
            ->orderByDesc('created_at')
            ->first();

        if (! $conversationLead instanceof Lead) {
            return null;
        }

        if ($this->concerns->isMateriallyDistinctConcern($conversationLead->concern, $concern)) {
            return null;
        }

        return $conversationLead;
    }
}
