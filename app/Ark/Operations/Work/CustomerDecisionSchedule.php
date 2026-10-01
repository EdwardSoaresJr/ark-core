<?php

namespace App\Ark\Operations\Work;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'created_by_user_id',
    'customer_id',
    'repair_order_id',
    'scheduled_for',
    'notes',
    'cleared_at',
])]
class CustomerDecisionSchedule extends Model
{
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'cleared_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function isActive(): bool
    {
        return $this->cleared_at === null;
    }

    /** First calendar day this decision should reappear (day before scheduled). */
    public function reminderStartsOn(): Carbon
    {
        return $this->scheduled_for->copy()->startOfDay()->subDay();
    }

    public function isSnoozed(?Carbon $now = null): bool
    {
        $now ??= now();

        return $now->copy()->startOfDay()->lt($this->reminderStartsOn());
    }

    public function scheduledForLabel(): string
    {
        $date = $this->scheduled_for->copy()->startOfDay();
        $today = now()->startOfDay();

        if ($date->equalTo($today)) {
            return 'Scheduled today';
        }

        if ($date->equalTo($today->copy()->addDay())) {
            return 'Scheduled tomorrow';
        }

        return 'Scheduled '.$date->format('D M j');
    }
}
