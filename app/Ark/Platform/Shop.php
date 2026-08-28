<?php

namespace App\Ark\Platform;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Platform Shop authority — not operational shop_settings.
 *
 * M2: owned by one User (hasOne). Provisioning / Tenant remain later milestones.
 *
 * @see docs/platform/shop-authority-v1.md
 * @see docs/platform/cloud-saas-critical-path-v1.md
 */
class Shop extends Model
{
    protected $table = 'platform_shops';

    protected $fillable = [
        'uuid',
        'owner_user_id',
        'slug',
        'legal_name',
        'display_name',
        'phone',
        'email',
        'timezone',
        'country',
        'state',
        'city',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShopStatus::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function deployment(): HasOne
    {
        return $this->hasOne(Deployment::class);
    }

    public function clusterAssignments(): HasMany
    {
        return $this->hasMany(ClusterAssignment::class);
    }

    public function provisioningRequests(): HasMany
    {
        return $this->hasMany(ProvisioningRequest::class);
    }
}
