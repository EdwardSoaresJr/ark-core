<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\OutboundDeliveryMode;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'type',
    'status',
    'scheduled_for',
    'repair_order_id',
    'customer_id',
    'conversation_id',
    'requested_by_user_id',
    'delivery_mode',
    'recipient_phone',
    'recipient_email',
    'payload_json',
    'requested_at',
    'sent_at',
    'failed_at',
    'failure_reason',
    'cancelled_at',
    'cancelled_by_user_id',
])]
class ScheduledOutboundMessage extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ScheduledOutboundMessageType::class,
            'status' => ScheduledOutboundMessageStatus::class,
            'delivery_mode' => OutboundDeliveryMode::class,
            'payload_json' => 'array',
            'scheduled_for' => 'datetime',
            'requested_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function isScheduled(): bool
    {
        return $this->status === ScheduledOutboundMessageStatus::Scheduled;
    }
}
