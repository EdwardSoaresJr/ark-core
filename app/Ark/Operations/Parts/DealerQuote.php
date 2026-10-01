<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class DealerQuote extends Model
{
    protected $fillable = [
        'repair_order_id',
        'supplier_name',
        'quote_number',
        'vehicle_description',
        'vin',
        'dealer_total_cents',
        'original_filename',
        'storage_path',
        'raw_text',
        'captured_by_user_id',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'dealer_total_cents' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DealerQuoteLine::class)->orderBy('position');
    }

    public function hasOriginalDocument(): bool
    {
        return filled($this->storage_path) && Storage::disk('local')->exists($this->storage_path);
    }

    public function sourceHeadline(): string
    {
        $supplier = trim((string) $this->supplier_name);
        $quote = trim((string) $this->quote_number);

        if ($supplier !== '' && $quote !== '') {
            return $supplier.' · Quote '.$quote;
        }

        if ($supplier !== '') {
            return $supplier;
        }

        if ($quote !== '') {
            return 'Quote '.$quote;
        }

        return 'Dealer Quote';
    }
}
