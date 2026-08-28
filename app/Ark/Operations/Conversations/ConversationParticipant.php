<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Operations\Customers\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'conversation_id',
    'participant_type',
    'customer_id',
    'user_id',
    'display_name',
])]
class ConversationParticipant extends Model
{
    protected function casts(): array
    {
        return [
            'participant_type' => ConversationParticipantType::class,
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function displayLabel(): string
    {
        if (filled($this->display_name)) {
            return $this->display_name;
        }

        if ($this->participant_type === ConversationParticipantType::Advisor && $this->user) {
            return $this->user->name;
        }

        if ($this->participant_type === ConversationParticipantType::Customer && $this->customer) {
            return $this->customer->name;
        }

        return $this->participant_type->label();
    }
}
