<?php

namespace App\Ark\Operations\Conversations;

use App\Ark\Growth\Models\GrowthSession;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Customers\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'growth_session_id',
    'contact_surface',
    'contact_address',
    'status',
    'owned_by_user_id',
    'waiting_on',
    'posture_changed_at',
    'resolved_at',
    'follow_up_due_at',
    'reopen_count',
])]
class Conversation extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Conversation $conversation): void {
            if ($conversation->status === ConversationStatus::Resolved
                && $conversation->waiting_on === ConversationWaitingOn::Shop) {
                $conversation->waiting_on = ConversationWaitingOn::Customer;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'contact_surface' => ConversationContactSurface::class,
            'status' => ConversationStatus::class,
            'waiting_on' => ConversationWaitingOn::class,
            'posture_changed_at' => 'datetime',
            'resolved_at' => 'datetime',
            'follow_up_due_at' => 'datetime',
            'reopen_count' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpenPosture(Builder $query): Builder
    {
        return $query->where('status', ConversationStatus::Open->value);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owned_by_user_id');
    }

    public function growthSession(): BelongsTo
    {
        return $this->belongsTo(GrowthSession::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function latestInboundMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)->ofMany(
            ['occurred_at' => 'max', 'id' => 'max'],
            fn (Builder $query) => $query->where('direction', OperationalCommunicationDirection::Inbound->value),
        );
    }

    public function links(): HasMany
    {
        return $this->hasMany(ConversationLink::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ConversationRead::class);
    }

    /**
     * Whether this conversation is with that customer. A link records what a conversation concerns (a repair
     * order's customer, for example); an email thread is the customer's only at their own email address, so a
     * document emailed to a warranty company from the customer's repair order stays the warranty company's.
     */
    public function isWithCustomer(Customer $customer): bool
    {
        if ($this->contact_surface !== ConversationContactSurface::Email) {
            return true;
        }

        $email = strtolower(trim((string) $customer->email));

        return $email !== '' && $email === strtolower(trim((string) $this->contact_address));
    }

    /**
     * The customer this conversation is with, among the customers linked to it.
     */
    public function linkedCustomerId(): ?int
    {
        $ids = ConversationLink::query()
            ->where('conversation_id', $this->id)
            ->where('linkable_type', (new Customer)->getMorphClass())
            ->orderBy('id')
            ->pluck('linkable_id');

        if ($this->contact_surface !== ConversationContactSurface::Email) {
            return $ids->isEmpty() ? null : (int) $ids->first();
        }

        $customer = Customer::query()
            ->whereIn('id', $ids->all())
            ->get()
            ->first(fn (Customer $customer): bool => $this->isWithCustomer($customer));

        return $customer?->id;
    }
}
