<?php

use App\Ark\Operations\Messaging\TwilioMessagingWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Twilio → Core messaging. CSRF is excepted for webhooks/* in bootstrap/app.php.
| The shop number and signature select this installation. Platform is not involved.
*/

Route::post('/webhooks/twilio/messaging/inbound', [TwilioMessagingWebhookController::class, 'inbound'])
    ->name('webhooks.twilio.messaging.inbound');

Route::post('/webhooks/twilio/messaging/status', [TwilioMessagingWebhookController::class, 'status'])
    ->name('webhooks.twilio.messaging.status');
