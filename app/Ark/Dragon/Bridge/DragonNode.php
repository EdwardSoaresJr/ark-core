<?php

namespace App\Ark\Dragon\Bridge;

use App\Ark\Dragon\DragonServiceToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DragonNode extends Model
{
    public const HEARTBEAT_OFFLINE_SECONDS = 90;

    protected $table = 'dragon_nodes';

    protected $fillable = [
        'public_id',
        'name',
        'enabled',
        'capabilities',
        'dragon_service_token_id',
        'version',
        'metadata',
        'connected_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'capabilities' => 'array',
            'metadata' => 'array',
            'connected_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $node): void {
            if (! filled($node->public_id)) {
                $node->public_id = (string) Str::uuid();
            }
        });
    }

    public function serviceToken(): BelongsTo
    {
        return $this->belongsTo(DragonServiceToken::class, 'dragon_service_token_id');
    }

    public function isOnline(?Carbon $now = null): bool
    {
        if (! $this->enabled || $this->last_seen_at === null) {
            return false;
        }

        $now ??= Carbon::now();

        return $this->last_seen_at->gte($now->copy()->subSeconds(self::HEARTBEAT_OFFLINE_SECONDS));
    }

    public function supports(string $capability): bool
    {
        $caps = $this->capabilities ?? [];

        return in_array($capability, $caps, true);
    }

    /**
     * @param  list<string>  $capabilities
     */
    public function markConnected(array $capabilities, ?string $version = null): void
    {
        $now = Carbon::now();

        $this->forceFill([
            'capabilities' => array_values(array_unique($capabilities)),
            'version' => $version,
            'connected_at' => $this->connected_at ?? $now,
            'last_seen_at' => $now,
        ])->save();
    }

    public function touchHeartbeat(): void
    {
        $this->forceFill(['last_seen_at' => Carbon::now()])->save();
    }

    public static function findOrCreateForToken(DragonServiceToken $token, string $name = 'arkai'): self
    {
        /** @var self|null $existing */
        $existing = static::query()->where('dragon_service_token_id', $token->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        return static::query()->create([
            'name' => $name,
            'enabled' => true,
            'capabilities' => [],
            'dragon_service_token_id' => $token->id,
        ]);
    }
}
