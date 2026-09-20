<?php

namespace App\Ark\Operations\Documents;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentEvent extends Model
{
    protected $table = 'document_events';

    protected $fillable = [
        'document_id',
        'type',
        'actor_user_id',
        'meta',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentEventType::class,
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
