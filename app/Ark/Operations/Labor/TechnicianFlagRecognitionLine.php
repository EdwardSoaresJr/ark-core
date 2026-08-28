<?php

namespace App\Ark\Operations\Labor;

use App\Ark\Operations\RepairOrders\RepairOrderLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianFlagRecognitionLine extends Model
{
    protected $table = 'technician_flag_recognition_lines';

    protected $fillable = [
        'technician_flag_recognition_id',
        'repair_order_line_id',
        'description',
        'line_type',
        'flag_hours',
        'operation_id',
    ];

    protected function casts(): array
    {
        return [
            'flag_hours' => 'decimal:2',
            'operation_id' => 'integer',
        ];
    }

    public function recognition(): BelongsTo
    {
        return $this->belongsTo(TechnicianFlagRecognition::class, 'technician_flag_recognition_id');
    }

    public function repairOrderLine(): BelongsTo
    {
        return $this->belongsTo(RepairOrderLine::class, 'repair_order_line_id');
    }
}
