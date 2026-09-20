<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inspection extends Model
{
    public const REAR_AXLE_DISC = 'disc';

    public const REAR_AXLE_DRUM = 'drum';

    protected $fillable = [
        'repair_order_id',
        'inspection_template_id',
        'previous_inspection_template_id',
        'template_correction_reason',
        'template_corrected_at',
        'notes',
        'rear_axle_brake_type',
        'recorded_by_user_id',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'template_corrected_at' => 'datetime',
        ];
    }

    public function repairOrder(): BelongsTo
    {
        return $this->belongsTo(RepairOrder::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'inspection_template_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class)->orderBy('position')->orderBy('id');
    }

    public function activeItems(): HasMany
    {
        return $this->items()->whereNull('superseded_at');
    }

    public function previousTemplate(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'previous_inspection_template_id');
    }

    public function hasCapturedEvidence(): bool
    {
        if ($this->items()->whereNull('superseded_at')
            ->where('observed_state', '!=', InspectionObservedState::NotChecked->value)
            ->exists()) {
            return true;
        }

        if ($this->items()->whereNull('superseded_at')->whereHas('measurements')->exists()) {
            return true;
        }

        if ($this->items()->whereNull('superseded_at')->whereHas('photos')->exists()) {
            return true;
        }

        return $this->items()->whereNull('superseded_at')
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->exists();
    }

    public function retainedHistoryCount(): int
    {
        return (int) $this->items()->whereNotNull('superseded_at')->count();
    }
}
