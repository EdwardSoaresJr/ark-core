<?php

namespace App\Ark\Runtime\Identity\Oidc;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProductAccess extends Model
{
    protected $table = 'user_product_access';

    protected $fillable = [
        'user_id',
        'product_slug',
        'granted',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
