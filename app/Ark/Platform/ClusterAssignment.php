<?php

namespace App\Ark\Platform;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Authority for Shop → Cluster placement decisions (with history).
 *
 * @see docs/platform/cluster-assignment-authority-v1.md
 */
class ClusterAssignment extends Model
{
    protected $table = 'platform_cluster_assignments';

    protected $fillable = [
        'shop_id',
        'deployment_id',
        'cluster_id',
        'profile',
        'source',
        'reason',
        'assigned_by_user_id',
        'previous_cluster_id',
        'assigned_at',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'profile' => DeploymentProfile::class,
            'source' => ClusterAssignmentSource::class,
            'assigned_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class);
    }

    public function previousCluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class, 'previous_cluster_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function isCurrent(): bool
    {
        return $this->superseded_at === null;
    }
}
