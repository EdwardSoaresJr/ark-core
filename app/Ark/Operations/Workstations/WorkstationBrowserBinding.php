<?php

namespace App\Ark\Operations\Workstations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WorkstationBrowserBinding extends Model
{
    protected $fillable = [
        'workstation_id',
        'token',
        'last_seen_at',
        'known_operator_user_ids',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'known_operator_user_ids' => 'array',
        ];
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class);
    }

    public static function issueForWorkstation(Workstation $workstation): self
    {
        return self::query()->create([
            'workstation_id' => $workstation->id,
            'token' => Str::random(48),
            'last_seen_at' => now(),
        ]);
    }

    public function touchSeen(bool $force = false): void
    {
        // Binding presence is recovered by the staff heartbeat POST — do not WRITE
        // on every GET when last_seen was updated recently (Read/Write rule).
        if (! $force && $this->last_seen_at !== null && $this->last_seen_at->greaterThan(now()->subMinutes(5))) {
            return;
        }

        $this->forceFill(['last_seen_at' => now()])->save();
    }
}
