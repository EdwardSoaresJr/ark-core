<?php

namespace App\Ark\Dragon\Agent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DragonKnowledgeDocument extends Model
{
    protected $table = 'dragon_knowledge_documents';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'source_id',
        'title',
        'source_ref',
        'body',
        'content_hash',
        'ingested_at',
    ];

    protected function casts(): array
    {
        return [
            'ingested_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(DragonKnowledgeSource::class, 'source_id');
    }
}
