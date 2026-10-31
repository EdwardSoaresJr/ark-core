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
     *
     * @return array<int, array{id: int, description: string, subtotal_cents: int}>
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
                'subtotal_cents' => (int) $line['subtotal_cents'],
            ];
        }

        return $lines;
    }
}
