<?php

namespace App\Ark\Operations\Inspections;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionTemplateItem extends Model
{
    protected $fillable = [
        'inspection_template_category_id',
        'label',
        'point_key',
        'position',
        'requires_photo',
        'requires_scan_evidence',
        'measurement_name',
        'measurement_unit',
        'measurement_slots',
        'builder_meta',
        'gate_group',
        'axle_role',
        'allows_na',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'requires_photo' => 'boolean',
            'requires_scan_evidence' => 'boolean',
            'allows_na' => 'boolean',
            'enabled' => 'boolean',
            'measurement_slots' => 'array',
            'builder_meta' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplateCategory::class, 'inspection_template_category_id');
    }
}
