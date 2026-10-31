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
        'approved_lines',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'line_ids' => 'array',
            'approved_lines' => 'array',
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
     * What the customer approved on each line. Empty for scopes recorded before amounts were kept.
     * `amounts` holds every priced amount the line carried at approval; null for entries that kept only the subtotal.
     *
     * @return array<int, array{id: int, description: string, customer_description: string, subtotal_cents: int, amounts: array{quantity: string, unit_price_cents: int, subtotal_cents: int, standing_discount_cents: int, shop_fee_cents: int, tax_cents: int, total_cents: int}|null}>
     */
    public function approvedLines(): array
    {
        $lines = [];

        foreach (is_array($this->approved_lines) ? $this->approved_lines : [] as $line) {
            if (! is_array($line) || ! isset($line['id'], $line['subtotal_cents'])) {
                continue;
            }

            $lines[(int) $line['id']] = [
                'id' => (int) $line['id'],
                'description' => (string) ($line['description'] ?? ''),
                'customer_description' => (string) ($line['customer_description'] ?? ''),
                'subtotal_cents' => (int) $line['subtotal_cents'],
                'amounts' => self::recordedAmounts($line),
            ];
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{quantity: string, unit_price_cents: int, subtotal_cents: int, standing_discount_cents: int, shop_fee_cents: int, tax_cents: int, total_cents: int}|null
     */
    public static function recordedAmounts(array $line): ?array
    {
        if (! isset($line['total_cents'], $line['quantity'], $line['unit_price_cents'])) {
            return null;
        }

        return [
            'quantity' => (string) $line['quantity'],
            'unit_price_cents' => (int) $line['unit_price_cents'],
            'subtotal_cents' => (int) $line['subtotal_cents'],
            'standing_discount_cents' => (int) ($line['standing_discount_cents'] ?? 0),
            'shop_fee_cents' => (int) ($line['shop_fee_cents'] ?? 0),
            'tax_cents' => (int) ($line['tax_cents'] ?? 0),
            'total_cents' => (int) $line['total_cents'],
        ];
    }
}
