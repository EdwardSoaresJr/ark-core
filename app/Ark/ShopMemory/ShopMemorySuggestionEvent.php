<?php

namespace App\Ark\ShopMemory;

use App\Ark\ShopMemory\Suggestion\SuggestionOutcome;
use Illuminate\Database\Eloquent\Model;

/**
 * Write-only observation capture. Not consumed by ranking.
 */
final class ShopMemorySuggestionEvent extends Model
{
    protected $table = 'shop_memory_suggestion_events';

    protected $fillable = [
        'provider_key',
        'suggestion_id',
        'outcome',
        'surface',
        'query',
        'repair_order_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => SuggestionOutcome::class,
        ];
    }
}
