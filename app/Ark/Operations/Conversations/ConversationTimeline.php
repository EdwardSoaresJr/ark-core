<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Collection;

class ConversationTimeline
{
    /**
     * @return Collection<int, ConversationMessage>
     */
    public function forPhone(string $normalizedPhone, int $limit = 8): Collection
    {
        $conversation = Conversation::query()
            ->where('contact_surface', ConversationContactSurface::Phone)
            ->where('contact_address', $normalizedPhone)
            ->first();

        if ($conversation === null) {
            return collect();
        }

        return $this->messagesForConversations(collect([$conversation->id]), $limit);
    }

    /**
     * Customer-relationship projection: messages on phone conversation plus any conversation linked to customer.
     *
     * @return Collection<int, ConversationMessage>
     */
    public function forCustomerRelationship(Customer $customer, ?string $normalizedPhone = null, int $limit = 8): Collection
    {
        $conversationIds = ConversationLink::query()
            ->where('linkable_type', $customer->getMorphClass())
            ->where('linkable_id', $customer->id)
            ->pluck('conversation_id');

        if ($normalizedPhone !== null) {
            $phoneConversation = Conversation::query()
                ->where('contact_surface', ConversationContactSurface::Phone)
                ->where('contact_address', $normalizedPhone)
                ->value('id');

            if ($phoneConversation !== null) {
                $conversationIds = $conversationIds->push($phoneConversation);
            }
        }

        return $this->messagesForConversations($conversationIds->unique()->values(), $limit);
    }

    /**
     * @return Collection<int, ConversationMessage>
     */
    public function forCustomerRelationshipSince(
        Customer $customer,
        int $sinceMessageId,
        ?string $normalizedPhone = null,
        int $limit = 20,
    ): Collection {
        $conversationIds = ConversationLink::query()
            ->where('linkable_type', $customer->getMorphClass())
            ->where('linkable_id', $customer->id)
            ->pluck('conversation_id');

        if ($normalizedPhone !== null) {
            $phoneConversation = Conversation::query()
                ->where('contact_surface', ConversationContactSurface::Phone)
                ->where('contact_address', $normalizedPhone)
                ->value('id');

            if ($phoneConversation !== null) {
                $conversationIds = $conversationIds->push($phoneConversation);
            }
        }

        $conversationIds = $conversationIds->unique()->values();

        if ($conversationIds->isEmpty()) {
            return collect();
        }

        return ConversationMessage::query()
            ->with(['participant.user', 'participant.customer', 'attachments'])
            ->whereIn('conversation_id', $conversationIds)
            ->when($sinceMessageId > 0, fn ($query) => $query->where('id', '>', $sinceMessageId))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, ConversationMessage>
     */
    public function forRepairOrder(RepairOrder $repairOrder, int $limit = 12): Collection
    {
        $conversationIds = ConversationLink::query()
            ->where('linkable_type', $repairOrder->getMorphClass())
            ->where('linkable_id', $repairOrder->id)
            ->pluck('conversation_id');

        return $this->messagesForConversations($conversationIds, $limit);
    }

    /**
     * @param  Collection<int, int|string>  $conversationIds
     * @return Collection<int, ConversationMessage>
     */
    private function messagesForConversations(Collection $conversationIds, int $limit): Collection
    {
        if ($conversationIds->isEmpty()) {
            return collect();
        }

        return ConversationMessage::query()
            ->with(['participant.user', 'participant.customer', 'attachments'])
            ->whereIn('conversation_id', $conversationIds)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
