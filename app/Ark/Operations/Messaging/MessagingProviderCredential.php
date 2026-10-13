<?php

namespace App\Ark\Operations\Messaging;

use Illuminate\Database\Eloquent\Model;

class MessagingProviderCredential extends Model
{
    protected $fillable = [
        'provider',
        'account_sid',
        'auth_token',
        'from_number',
        'api_base',
    ];

    protected function casts(): array
    {
        return [
            'auth_token' => 'encrypted',
        ];
    }
}
