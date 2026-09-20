<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArkademyContentRegistry extends Model
{
    protected $table = 'arkademy_content_registry';

    protected $fillable = [
        'source_type',
        'bookstack_id',
        'bookstack_url',
        'visibility',
        'legacy_key',
        'title',
        'promoted_at',
        'promoted_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'promoted_at' => 'datetime',
        ];
    }

    public function promotedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoted_by_user_id');
    }

    public static function findByLegacyKey(string $legacyKey): ?self
    {
        return self::query()
            ->where('source_type', 'page')
            ->where('legacy_key', $legacyKey)
            ->first();
    }

    public static function findByBookStackId(string $sourceType, int $bookstackId): ?self
    {
        return self::query()
            ->where('source_type', $sourceType)
            ->where('bookstack_id', $bookstackId)
            ->first();
    }
}
