<?php

namespace App\Ark\Operations\Attention;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdvisorNudgeResponse extends Model
{
    protected $fillable = [
        'user_id',
        'entity_key',
        'nudge_key',
        'response',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'response' => AdvisorNudgeResponseKind::class,
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return Collection<int, string>
     */
    public static function suppressedNudgeKeys(int $userId, string $entityKey, Carbon $since): Collection
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('entity_key', $entityKey)
            ->where('created_at', '>=', $since)
            ->pluck('nudge_key');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForEntity(Builder $query, int $userId, string $entityKey): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->where('entity_key', $entityKey);
    }
}
