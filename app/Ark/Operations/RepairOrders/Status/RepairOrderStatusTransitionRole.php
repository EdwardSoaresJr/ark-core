<?php

namespace App\Ark\Operations\RepairOrders\Status;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairOrderStatusTransitionRole extends Model
{
    protected $table = 'ro_status_trans_roles';

    protected $fillable = [
        'transition_id',
        'role',
    ];

    public function transition(): BelongsTo
    {
        return $this->belongsTo(RepairOrderStatusTransition::class, 'transition_id');
    }
}
