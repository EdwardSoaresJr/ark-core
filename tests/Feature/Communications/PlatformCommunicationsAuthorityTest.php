<?php

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Communications\ManagedCommunicationsGate;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    ShopSettings::current()->persistTrusted([
        'platform_status' => 'connected',
        'platform_credential' => 'retired-platform-credential',
        'platform_base_url' => 'https://cloud.test',
    ]);
    config()->set('services.twilio.auth_token', 'authority-test-token');
    Http::fake();
});

function postSignedInboundSms(array $params): Illuminate\Testing\TestResponse
{
    $url = 'http://localhost/webhooks/communications/twilio/messaging/incoming';
    ksort($params, SORT_STRING);
    $data = $url;
    foreach ($params as $key => $value) {
        $data .= $key.$value;
    }

    return test()->call('POST', $url, $params, [], [], [
        'HTTP_X_TWILIO_SIGNATURE' => base64_encode(hash_hmac('sha1', $data, 'authority-test-token', true)),
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
    ], http_build_query($params));
}

test('stale Platform connection data keeps the retired Platform as authority by default', function (): void {
    expect(ManagedCommunicationsGate::platformConnected())->toBeTrue()
        ->and(ManagedCommunicationsGate::platformInbox())->toBeTrue()
        ->and(ManagedCommunicationsGate::coreMirrorEnabled())->toBeFalse();
});

test('turning Platform communications authority off records inbound texts in Core despite stale connection data', function (): void {
    config()->set('services.ark_platform.communications_authority', false);

    expect(ManagedCommunicationsGate::platformInbox())->toBeFalse()
        ->and(ManagedCommunicationsGate::platformSend())->toBeFalse()
        ->and(ManagedCommunicationsGate::coreMirrorEnabled())->toBeTrue();

    postSignedInboundSms([
        'MessageSid' => 'SMauthority0000000000000000000001',
        'From' => '+17195550142',
        'To' => '+17195556227',
        'Body' => 'Is my truck ready?',
    ])->assertOk();

    $message = ConversationMessage::query()->sole();
    expect($message->body)->toBe('Is my truck ready?')
        ->and($message->metadata['twilio_message_sid'] ?? null)->toBe('SMauthority0000000000000000000001');
    Http::assertNothingSent();
});

test('the authority switch is read from ARK_PLATFORM_COMMUNICATIONS_AUTHORITY', function (): void {
    $config = file_get_contents(base_path('config/services.php'));

    expect($config)->toContain("'communications_authority' => env('ARK_PLATFORM_COMMUNICATIONS_AUTHORITY', true)");
});
