<?php

namespace App\Ark\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Deployment projection of a Shop. Current cluster_id points at latest assignment.
 *
 * @see docs/platform/cluster-assignment-authority-v1.md
 */
class Deployment extends Model
{
    protected $table = 'platform_deployments';

    protected $fillable = [
        'shop_id',
        'cluster_id',
        'profile',
    ];

    protected function casts(): array
    {
        return [
            'profile' => DeploymentProfile::class,
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ClusterAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(ClusterAssignment::class)->whereNull('superseded_at')->latestOfMany('assigned_at');
    }

    public function provisioningRequests(): HasMany
    {
        return $this->hasMany(ProvisioningRequest::class);
    }
}
