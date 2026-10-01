<?php

namespace App\Ark\Operations\Workboard;

use Illuminate\Database\Eloquent\Model;

class JobBoardLane extends Model
{
    protected $table = 'job_board_lanes';

    protected $fillable = [
        'key',
        'name',
        'color',
        'sort_order',
        'active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
