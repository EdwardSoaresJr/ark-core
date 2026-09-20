<?php

namespace App\Ark\Dragon\Assist;

use App\Ark\Dragon\Bridge\DragonNode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DragonAssistResult extends Model
{
    protected $table = 'dragon_assist_results';

    protected $fillable = [
        'dragon_assist_request_id',
        'dragon_node_id',
        'result_json',
        'model_name',
        'model_version',
        'knowledge_version',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'result_json' => 'array',
            'duration_ms' => 'integer',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(DragonAssistRequest::class, 'dragon_assist_request_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(DragonNode::class, 'dragon_node_id');
    }
}
