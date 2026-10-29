<?php

use App\Ark\Operations\Messaging\TwilioMessagingWebhookController;
use App\Ark\Operations\Telephony\TelephonyRecordingWebhookController;
use App\Ark\Operations\Telephony\TwilioSipOutboundController;
use App\Ark\Operations\Telephony\TwilioVoiceWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('webhooks/communications/twilio')->name('webhooks.communications.twilio.')->group(function (): void {
    Route::post('/messaging/incoming', [TwilioMessagingWebhookController::class, 'incoming'])->name('messaging.incoming');
    Route::post('/messaging/status', [TwilioMessagingWebhookController::class, 'status'])->name('messaging.status');
    Route::post('/voice/incoming', [TwilioVoiceWebhookController::class, 'incoming'])->name('voice.incoming');
    Route::post('/voice/status', [TwilioVoiceWebhookController::class, 'status'])->name('voice.status');
    Route::post('/voice/dial-complete', [TwilioVoiceWebhookController::class, 'dialComplete'])->name('voice.dial-complete');
    Route::post('/voice/recording', TelephonyRecordingWebhookController::class)->name('voice.recording');
    Route::post('/voice/ring-status/{parentCallSid}/{endpointId}', [TwilioVoiceWebhookController::class, 'ringStatus'])
        ->whereNumber('endpointId')
        ->name('voice.ring-status');
    Route::post('/voice/conference-join', [TwilioVoiceWebhookController::class, 'conferenceJoin'])->name('voice.conference-join');
    Route::post('/voice/sip-outbound', [TwilioSipOutboundController::class, 'dial'])->name('voice.sip-outbound');
    Route::post('/voice/sip-outbound/complete', [TwilioSipOutboundController::class, 'complete'])->name('voice.sip-outbound-complete');
    Route::post('/voice/sip-outbound/notice', [TwilioSipOutboundController::class, 'notice'])->name('voice.sip-outbound-notice');
});
