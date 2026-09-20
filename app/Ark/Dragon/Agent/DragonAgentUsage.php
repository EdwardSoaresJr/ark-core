<?php

namespace App\Ark\Dragon\Agent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DragonAgentUsage extends Model
{
    protected $fillable = [
        'dragon_agent_conversation_id',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'tool_calls',
        'latency_ms',
        'estimated_cost_cents',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DragonAgentConversation::class, 'dragon_agent_conversation_id');
    }
}
