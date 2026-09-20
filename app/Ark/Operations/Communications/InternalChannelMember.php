<?php

namespace App\Ark\Operations\Communications;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'internal_channel_id',
    'user_id',
    'role',
    'last_read_at',
])]
class InternalChannelMember extends Model
{
    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(InternalChannel::class, 'internal_channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
