<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionItem extends Model
{
    protected $fillable = [
        'inspection_id',
        'repair_order_concern_id',
        'category',
        'checklist_category_name',
        'walk_section',
        'label',
        'observed_state',
        'notes',
        'selected_observations',
        'position',
        'inspection_template_item_id',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'observed_state' => InspectionObservedState::class,
            'category' => InspectionItemCategory::class,
            'selected_observations' => 'array',
            'superseded_at' => 'datetime',
        ];
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_at !== null;
    }

    public function scopeActive($query)
    {
        return $query->whereNull('superseded_at');
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function concern(): BelongsTo
    {
        return $this->belongsTo(RepairOrderConcern::class, 'repair_order_concern_id');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(InspectionItemMeasurement::class)->orderBy('position')->orderBy('id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(InspectionItemPhoto::class)->orderBy('id');
    }

    public function categoryLabel(): string
    {
        if (filled($this->checklist_category_name)) {
            return (string) $this->checklist_category_name;
        }

        $category = $this->category;

        return $category instanceof InspectionItemCategory
            ? $category->label()
            : (string) $category;
    }

    public function observedStateLabel(): string
    {
        $state = $this->observed_state;

        return $state instanceof InspectionObservedState
            ? $state->label()
            : (string) $state;
    }
}
