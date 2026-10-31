<?php

namespace App\Ark\Operations\RepairOrders;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ApprovedWorkScope extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'repair_order_id',
        'repair_order_concern_id',
        'line_ids',
        'line_amounts',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'line_ids' => 'array',
            'line_amounts' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): bool => throw new LogicException('Approved work scopes are append-only.'));
        static::deleting(fn (): bool => throw new LogicException('Approved work scopes are append-only.'));
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(RepairOrderConcern::class, 'repair_order_concern_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /**
     * @return list<int>
     */
    public function lineIds(): array
    {
        return array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            is_array($this->line_ids) ? $this->line_ids : [],
        ));
    }

    /**
     * Amounts each line carried when this approval was recorded. Null for
     * approvals recorded before amounts were kept.
     *
     * @return array<int, array{quantity: string, unit_price_cents: int, subtotal_cents: int, standing_discount_cents: int, shop_fee_cents: int, tax_cents: int, total_cents: int}>|null
     */
    public function lineAmounts(): ?array
    {
        if (! is_array($this->line_amounts)) {
            return null;
        }

        $amounts = [];

        foreach ($this->line_amounts as $lineId => $line) {
            if (! is_array($line)) {
                continue;
            }

            $amounts[(int) $lineId] = [
                'quantity' => (string) ($line['quantity'] ?? '0'),
                'unit_price_cents' => (int) ($line['unit_price_cents'] ?? 0),
                'subtotal_cents' => (int) ($line['subtotal_cents'] ?? 0),
                'standing_discount_cents' => (int) ($line['standing_discount_cents'] ?? 0),
                'shop_fee_cents' => (int) ($line['shop_fee_cents'] ?? 0),
                'tax_cents' => (int) ($line['tax_cents'] ?? 0),
                'total_cents' => (int) ($line['total_cents'] ?? 0),
            ];
        }

        return $amounts;
    }
}
