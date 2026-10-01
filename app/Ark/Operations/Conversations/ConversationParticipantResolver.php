<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Customers\Customer;
use App\Models\User;

class ConversationParticipantResolver
{
    public function system(Conversation $conversation, ?string $displayName = null): ConversationParticipant
    {
        return $this->resolve($conversation, ConversationParticipantType::System, displayName: $displayName);
    }

    public function advisor(Conversation $conversation, User $user): ConversationParticipant
    {
        return $this->resolve(
            $conversation,
            ConversationParticipantType::Advisor,
            user: $user,
            displayName: $user->name,
        );
    }

    public function customer(
        Conversation $conversation,
        ?Customer $customer = null,
        ?string $displayName = null,
    ): ConversationParticipant {
        return $this->resolve(
            $conversation,
            ConversationParticipantType::Customer,
            customer: $customer,
            displayName: $displayName ?? $customer?->name,
        );
    }

    private function resolve(
        Conversation $conversation,
        ConversationParticipantType $type,
        ?Customer $customer = null,
        ?User $user = null,
        ?string $displayName = null,
    ): ConversationParticipant {
        $query = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('participant_type', $type->value);

        if ($customer) {
            $query->where('customer_id', $customer->id);
        } elseif ($user) {
            $query->where('user_id', $user->id);
        } else {
            $query->whereNull('customer_id')->whereNull('user_id');
        }

        $existing = $query->first();

        if ($existing) {
            return $existing;
        }

        return ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'participant_type' => $type,
            'customer_id' => $customer?->id,
            'user_id' => $user?->id,
            'display_name' => $displayName,
        ]);
    }
}
