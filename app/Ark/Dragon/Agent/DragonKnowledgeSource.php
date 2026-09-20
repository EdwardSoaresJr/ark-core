<?php

namespace App\Ark\Dragon\Agent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DragonKnowledgeSource extends Model
{
    protected $table = 'dragon_knowledge_sources';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'source_type',
        'display_name',
        'authority_class',
        'origin',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(DragonKnowledgeDocument::class, 'source_id');
    }
}
