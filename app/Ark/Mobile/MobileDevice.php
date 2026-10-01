<?php

namespace App\Ark\Mobile;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileDevice extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'device_name',
        'platform',
        'app_version',
        'fcm_token',
        'voip_push_token',
        'last_seen_at',
        'voice_ready_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'voice_ready_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
