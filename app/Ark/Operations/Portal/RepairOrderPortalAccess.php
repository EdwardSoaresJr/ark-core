<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class RepairOrderPortalAccess extends Model
{
    protected $table = 'repair_order_portal_accesses';

    protected $fillable = [
        'repair_order_id',
        'public_code',
        'token_hash',
        'created_by_user_id',
        'revoked_at',
        'first_customer_viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
            'first_customer_viewed_at' => 'datetime',
        ];
    }

    public static function hashPlainToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function isActive(?Carbon $at = null): bool
    {
        return $this->revoked_at === null;
    }
}
