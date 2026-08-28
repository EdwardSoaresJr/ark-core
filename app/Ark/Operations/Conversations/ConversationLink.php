<?php

namespace App\Ark\Operations\Conversations;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'conversation_id',
    'linkable_type',
    'linkable_id',
])]
class ConversationLink extends Model
{
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
