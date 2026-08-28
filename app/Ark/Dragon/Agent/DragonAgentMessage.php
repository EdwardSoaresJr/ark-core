<?php

namespace App\Ark\Dragon\Agent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DragonAgentMessage extends Model
{
    protected $fillable = [
        'dragon_agent_conversation_id',
        'role',
        'content',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DragonAgentConversation::class, 'dragon_agent_conversation_id');
    }
}
