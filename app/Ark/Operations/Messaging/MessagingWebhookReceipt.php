<?php

namespace App\Ark\Operations\Messaging;

use Illuminate\Database\Eloquent\Model;

class MessagingWebhookReceipt extends Model
{
    protected $fillable = [
        'receipt_key',
    ];
}
