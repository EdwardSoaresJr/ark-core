<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'repair_order_id',
    'token_hash',
    'expires_at',
    'created_by_user_id',
    'revoked_at',
    'last_viewed_at',
])]
class EstimateAccessToken extends Model
{
    public static function hashPlainToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function createForPlainToken(RepairOrder $repairOrder, string $plainToken, array $attributes = []): self
    {
        return self::query()->create(array_merge([
            'repair_order_id' => $repairOrder->id,
            'token_hash' => self::hashPlainToken($plainToken),
        ], $attributes));
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isUsable(?Carbon $now = null): bool
    {
        $now ??= now();

        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at instanceof Carbon && $this->expires_at->lessThanOrEqualTo($now)) {
            return false;
        }

        return true;
    }
}
