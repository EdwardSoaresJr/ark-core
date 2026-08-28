<?php

namespace App\Ark\Platform;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Workflow authority for an attempt to provision infrastructure.
 * Not a Shop property. No Coolify/Stancl fields in v1.
 *
 * @see docs/platform/provisioning-request-authority-v1.md
 */
class ProvisioningRequest extends Model
{
    protected $table = 'platform_provisioning_requests';

    protected $fillable = [
        'shop_id',
        'deployment_id',
        'status',
        'source',
        'requested_by_user_id',
        'requested_at',
        'started_at',
        'completed_at',
        'failed_at',
        'failure_reason',
        'completed_steps',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProvisioningRequestStatus::class,
            'source' => ProvisioningRequestSource::class,
            'requested_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'completed_steps' => 'array',
        ];
    }

    /**
     * @return list<string>
     */
    public function completedStepKeys(): array
    {
        $steps = $this->completed_steps;

        if (! is_array($steps)) {
            return [];
        }

        return array_values(array_filter($steps, fn ($key) => is_string($key) && $key !== ''));
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
