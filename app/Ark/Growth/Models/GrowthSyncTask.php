<?php

namespace App\Ark\Growth\Models;

use App\Ark\Growth\Maintenance\GrowthSyncTaskKey;
use App\Ark\Growth\Maintenance\GrowthSyncTaskStatus;
use Illuminate\Database\Eloquent\Model;

class GrowthSyncTask extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'task_key';

    protected $keyType = 'string';

    protected $fillable = [
        'task_key',
        'status',
        'last_ran_at',
        'last_message',
        'last_metadata',
    ];

    protected function casts(): array
    {
        return [
            'task_key' => GrowthSyncTaskKey::class,
            'status' => GrowthSyncTaskStatus::class,
            'last_ran_at' => 'datetime',
            'last_metadata' => 'array',
        ];
    }

    public function isHealthy(): bool
    {
        return in_array($this->status, [GrowthSyncTaskStatus::Success, GrowthSyncTaskStatus::Skipped], true);
    }
}
