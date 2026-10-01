<?php

namespace App\Ark\Operations\RepairOrders\Status;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepairOrderStatusDefinition extends Model
{
    protected $table = 'ro_statuses';

    protected $fillable = [
        'slug',
        'name',
        'is_system',
        'requires_mileage_in',
        'requires_mileage_out',
        'dashboard_group_slug',
        'dashboard_group_name',
        'advisor_lane_key',
        'show_on_advisor_board',
        'show_on_technician_board',
        'is_terminal',
        'requires_variant',
        'enforce_standard_close_rules',
        'active',
        'sort_order',
        'customer_status_copy',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'requires_mileage_in' => 'boolean',
            'requires_mileage_out' => 'boolean',
            'show_on_advisor_board' => 'boolean',
            'show_on_technician_board' => 'boolean',
            'is_terminal' => 'boolean',
            'requires_variant' => 'boolean',
            'enforce_standard_close_rules' => 'boolean',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(RepairOrderStatusVariant::class, 'status_slug', 'slug')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function transitionsFrom(): HasMany
    {
        return $this->hasMany(RepairOrderStatusTransition::class, 'from_status_slug', 'slug');
    }
}
