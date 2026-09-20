<?php

namespace App\Ark\Platform;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cluster extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'status',
        'accepting_new_shops',
        'deployment_target',
        'ingress_endpoint',
        'current_version',
    ];

    protected function casts(): array
    {
        return [
            'type' => ClusterType::class,
            'status' => ClusterStatus::class,
            'accepting_new_shops' => 'boolean',
        ];
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ClusterAssignment::class);
    }

    /**
     * Observation — not a stored column.
     */
    public function currentShopCount(): int
    {
        return $this->deployments()->count();
    }

    public function scopeOfType(Builder $query, ClusterType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeShared(Builder $query): Builder
    {
        return $query->ofType(ClusterType::Shared);
    }

    public function scopeDedicated(Builder $query): Builder
    {
        return $query->ofType(ClusterType::Dedicated);
    }

    public function scopeHealthy(Builder $query): Builder
    {
        return $query->where('status', ClusterStatus::Healthy);
    }

    public function scopeAssignable(Builder $query): Builder
    {
        return $query->where('accepting_new_shops', true);
    }
}
