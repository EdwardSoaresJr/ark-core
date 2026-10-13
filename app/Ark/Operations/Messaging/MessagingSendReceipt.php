<?php

namespace App\Ark\Operations\Messaging;

use Illuminate\Database\Eloquent\Model;

class MessagingSendReceipt extends Model
{
    protected $fillable = [
        'receipt_key',
        'provider_message_sid',
    ];
}
