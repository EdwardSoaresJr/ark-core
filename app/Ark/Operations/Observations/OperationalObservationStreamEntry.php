<?php

namespace App\Ark\Operations\Observations;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Customers\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Active operational observation in the shared stream — interpretive, not authority.
 */
class OperationalObservationStreamEntry extends Model
{
    protected $table = 'operational_observation_stream';

    protected $fillable = [
        'observation_type',
        'dedupe_key',
        'customer_id',
        'conversation_id',
        'repair_order_id',
        'headline',
        'description',
        'source_event_name',
        'source_aggregate_type',
        'source_aggregate_id',
        'occurred_at',
        'resolved_at',
        'resolved_by_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
            'metadata' => 'array',
            'repair_order_id' => 'integer',
            'source_aggregate_id' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function observationType(): OperationalObservationType
    {
        return OperationalObservationType::from($this->observation_type);
    }

    public function isActive(): bool
    {
        return $this->resolved_at === null;
    }
}
