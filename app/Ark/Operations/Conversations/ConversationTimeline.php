<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
        return $this->customerRelationshipMessages($customer, $normalizedPhone)
            ?->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get() ?? collect();
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
        return $this->customerRelationshipMessages($customer, $normalizedPhone)
            ?->when($sinceMessageId > 0, fn ($query) => $query->where('id', '>', $sinceMessageId))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get() ?? collect();
    }

    /**
     * @return Collection<int, ConversationMessage>
     */
    /**
     * The messages about this repair order. A message names its repair order; one that names none belongs here only
     * when it is in the customer's own conversation. A warranty company's thread holds documents for many repair
     * orders, so its conversation being linked to this one does not make all of its messages this one's.
     *
     * @return Collection<int, ConversationMessage>
     */
    public function forRepairOrder(RepairOrder $repairOrder, int $limit = 12): Collection
    {
        $repairOrder->loadMissing('customer');

        $conversations = Conversation::query()
            ->whereIn('id', ConversationLink::query()
                ->where('linkable_type', $repairOrder->getMorphClass())
                ->where('linkable_id', $repairOrder->id)
                ->select('conversation_id'))
            ->get();

        if ($conversations->isEmpty()) {
            return collect();
        }

        $customerOwn = $repairOrder->customer === null
            ? collect()
            : $this->customerConversationIds($repairOrder->customer, $conversations);

        return ConversationMessage::query()
            ->with(['participant.user', 'participant.customer', 'attachments'])
            ->whereIn('conversation_id', $conversations->modelKeys())
            ->where(fn ($query) => $query
                ->where(fn ($tagged) => $this->aboutRepairOrders($tagged, [$repairOrder->id]))
                ->when($customerOwn->isNotEmpty(), fn ($query) => $query->orWhere(fn ($untagged) => $this
                    ->namingNoRepairOrder($untagged)
                    ->whereIn('conversation_id', $customerOwn->all()))))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, ConversationMessage>
     */
    public function forConversation(Conversation $conversation, int $limit = 12): Collection
    {
        return $this->messagesForConversations(collect([$conversation->id]), $limit);
    }

    /**
     * The customer's own conversations, whole, and from any other conversation about one of their repair orders (a
     * warranty company's, say) only the messages that name one of those repair orders: that thread may hold other
     * customers' documents too.
     *
     * @return Builder<ConversationMessage>|null
     */
    private function customerRelationshipMessages(Customer $customer, ?string $normalizedPhone): ?Builder
    {
        $repairOrderIds = RepairOrder::query()->where('customer_id', $customer->id)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $linked = ConversationLink::query()
            ->where(fn ($query) => $query
                ->where('linkable_type', $customer->getMorphClass())
                ->where('linkable_id', $customer->id))
            ->when($repairOrderIds !== [], fn ($query) => $query->orWhere(fn ($query) => $query
                ->where('linkable_type', (new RepairOrder)->getMorphClass())
                ->whereIn('linkable_id', $repairOrderIds)))
            ->select('conversation_id');

        $conversations = Conversation::query()
            ->whereIn('id', $linked)
            ->when($normalizedPhone !== null, fn ($query) => $query->orWhere(fn ($query) => $query
                ->where('contact_surface', ConversationContactSurface::Phone)
                ->where('contact_address', $normalizedPhone)))
            ->get();

        if ($conversations->isEmpty()) {
            return null;
        }

        $own = $this->customerConversationIds($customer, $conversations, $normalizedPhone);
        $others = $repairOrderIds === [] ? collect() : collect($conversations->modelKeys())->diff($own)->values();

        if ($own->isEmpty() && $others->isEmpty()) {
            return null;
        }

        return ConversationMessage::query()
            ->with(['participant.user', 'participant.customer', 'attachments'])
            ->where(fn ($query) => $query
                ->when($own->isNotEmpty(), fn ($query) => $query->whereIn('conversation_id', $own->all()))
                ->when($others->isNotEmpty(), fn ($query) => $query->orWhere(fn ($query) => $this
                    ->aboutRepairOrders($query->whereIn('conversation_id', $others->all()), $repairOrderIds))));
    }

    /**
     * Among these conversations, the ones that are the customer's own: their phone, a thread they take part in, or
     * one linked to them that is with them (an email thread at their own address).
     *
     * @param  EloquentCollection<int, Conversation>  $conversations
     * @return Collection<int, int>
     */
    private function customerConversationIds(Customer $customer, EloquentCollection $conversations, ?string $normalizedPhone = null): Collection
    {
        $phone = $normalizedPhone ?? PhoneNumber::normalize((string) ($customer->getAttributes()['phone'] ?? ''));
        $participating = ConversationParticipant::query()
            ->whereIn('conversation_id', $conversations->modelKeys())
            ->where('customer_id', $customer->id)
            ->pluck('conversation_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $linked = ConversationLink::query()
            ->whereIn('conversation_id', $conversations->modelKeys())
            ->where('linkable_type', $customer->getMorphClass())
            ->where('linkable_id', $customer->id)
            ->pluck('conversation_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $conversations
            ->filter(fn (Conversation $conversation): bool => in_array($conversation->id, $participating, true)
                || ($phone !== null && $conversation->contact_surface === ConversationContactSurface::Phone && $conversation->contact_address === $phone)
                || (in_array($conversation->id, $linked, true) && $conversation->isWithCustomer($customer)))
            ->map(fn (Conversation $conversation): int => $conversation->id)
            ->values();
    }

    /**
     * Messages that name one of these repair orders: `repair_order_id`, or a `repair_order_ids` list.
     *
     * @param  Builder<ConversationMessage>  $query
     * @param  list<int>  $repairOrderIds
     * @return Builder<ConversationMessage>
     */
    private function aboutRepairOrders(Builder $query, array $repairOrderIds): Builder
    {
        return $query->where(function ($query) use ($repairOrderIds): void {
            // Recorded as a number; older writers stored the id as a string.
            $query->whereIn('metadata->repair_order_id', [...$repairOrderIds, ...array_map('strval', $repairOrderIds)]);

            foreach ($repairOrderIds as $id) {
                $query->orWhereJsonContains('metadata->repair_order_ids', $id);
            }
        });
    }

    /**
     * @param  Builder<ConversationMessage>  $query
     * @return Builder<ConversationMessage>
     */
    private function namingNoRepairOrder(Builder $query): Builder
    {
        return $query->whereNull('metadata->repair_order_id')->whereNull('metadata->repair_order_ids');
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
