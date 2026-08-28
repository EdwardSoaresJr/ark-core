<?php

namespace App\Ark\Dragon\Agent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DragonAgentTrace extends Model
{
    protected $fillable = [
        'dragon_agent_conversation_id',
        'round',
        'tool',
        'arguments',
        'observation_summary',
        'data_categories',
        'latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'data_categories' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DragonAgentConversation::class, 'dragon_agent_conversation_id');
    }
}
