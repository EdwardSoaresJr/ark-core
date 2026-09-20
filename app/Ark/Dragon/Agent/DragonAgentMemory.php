<?php

namespace App\Ark\Dragon\Agent;

use App\Ark\Operations\Workstations\Workstation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DragonAgentMemory extends Model
{
    protected $table = 'dragon_agent_memories';

    protected $fillable = [
        'fact_key',
        'fact_value',
        'taught_by',
        'provenance',
        'superseded_at',
        'scope_type',
        'workstation_id',
        'user_id',
        'category',
        'supersedes_id',
    ];

    protected function casts(): array
    {
        return [
            'superseded_at' => 'datetime',
        ];
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->superseded_at === null;
    }
}
