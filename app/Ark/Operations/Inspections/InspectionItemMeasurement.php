<?php

namespace App\Ark\Operations\Inspections;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionItemMeasurement extends Model
{
    protected $fillable = [
        'inspection_item_id',
        'name',
        'value',
        'unit',
        'position',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InspectionItem::class, 'inspection_item_id');
    }

    public function formattedValue(): string
    {
        $unit = trim((string) ($this->unit ?? ''));

        return $unit === ''
            ? (string) $this->value
            : trim((string) $this->value).' '.$unit;
    }
}
