<?php

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Telephony\TelephonyEndpoint;
use App\Ark\Operations\Telephony\TwilioWebhookVerifier;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    config()->set('broadcasting.default', 'null');
    config()->set('services.twilio.account_sid', 'AC00000000000000000000000000000000');
    config()->set('services.twilio.auth_token', 'twilio-test-token');
    config()->set('services.twilio.from', '+17195550100');
    config()->set('services.ark_platform.communications_authority', true);
    TelephonyEndpoint::query()->delete();
});

/**
 * Posts the way Twilio does: a form-encoded body, signed over the URL and every sorted parameter.
 *
 * @param  array<string, string>  $params
 */
function twilioWebhook($test, string $url, array $params, ?string $signWithToken = 'twilio-test-token', ?array $signParams = null): TestResponse
{
    $signed = $signParams ?? $params;
    ksort($signed, SORT_STRING);
    $data = $url;
    foreach ($signed as $key => $value) {
        $data .= $key.$value;
    }
    $signature = $signWithToken === null ? '' : base64_encode(hash_hmac('sha1', $data, $signWithToken, true));

    $body = implode('&', array_map(
        static fn (string $key, string $value): string => rawurlencode($key).'='.rawurlencode($value),
        array_keys($params),
        array_values($params),
    ));

    return $test->call('POST', $url, $params, [], [], [
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'HTTP_X_TWILIO_SIGNATURE' => $signature,
    ], $body);
}

function inboundSms(string $sid, string $body, array $extra = []): array
{
    return array_merge([
        'AccountSid' => 'AC00000000000000000000000000000000',
        'MessageSid' => $sid,
        'SmsSid' => $sid,
        'From' => '+17195551234',
        'To' => '+17195550100',
        'Body' => $body,
        'NumMedia' => '0',
        'NumSegments' => '1',
        'ApiVersion' => '2010-04-01',
    ], $extra);
}

test('a signed inbound text is accepted and stored', function () {
    twilioWebhook($this, route('webhooks.communications.twilio.messaging.incoming'), inboundSms('SMsig0001', 'The truck is ready.'))->assertOk();

    expect(ConversationMessage::query()->where('body', 'The truck is ready.')->count())->toBe(1);
});

test('a customer text keeps its exact spacing and is never rejected for it', function (string $body) {
    twilioWebhook($this, route('webhooks.communications.twilio.messaging.incoming'), inboundSms('SMsig'.md5($body), $body))->assertOk();

    $stored = ConversationMessage::query()->latest('id')->value('body');
    expect($stored)->toBe($body);
})->with([
    'leading space' => ['  Is my car done?'],
    'trailing space' => ['Thanks, see you at 5 '],
    'trailing newline' => ["On my way\n"],
    'inner newlines' => ["Line one\nLine two\r\nLine three"],
    'unicode' => ['Gracias 👍 ¿Listo?'],
    'only spaces around emoji' => [' 🚗 '],
]);

test('empty optional parameters do not break the signature', function () {
    twilioWebhook($this, route('webhooks.communications.twilio.messaging.incoming'), inboundSms('SMsig0002', 'Ok', [
        'FromCity' => '',
        'FromState' => '',
        'FromZip' => '',
        'ToCity' => '',
    ]))->assertOk();

    expect(ConversationMessage::query()->where('body', 'Ok')->count())->toBe(1);
});

test('a bad, missing, or foreign signature is rejected and nothing is stored', function (?string $token, ?array $signedAs) {
    $params = inboundSms('SMsigbad1', 'This must not be stored.');

    twilioWebhook($this, route('webhooks.communications.twilio.messaging.incoming'), $params, $token, $signedAs)->assertStatus(401);

    expect(ConversationMessage::query()->where('body', 'This must not be stored.')->count())->toBe(0);
})->with([
    'missing signature' => [null, null],
    'another account token' => ['another-shop-token', null],
    'body changed after signing' => ['twilio-test-token', ['Body' => 'Something else'] + inboundSms('SMsigbad1', 'x')],
]);

test('a repeated delivery of the same text is stored once', function () {
    $params = inboundSms('SMsigdup1', 'Call me back please ');

    twilioWebhook($this, route('webhooks.communications.twilio.messaging.incoming'), $params)->assertOk();
    twilioWebhook($this, route('webhooks.communications.twilio.messaging.incoming'), $params)->assertOk();

    expect(ConversationMessage::query()->where('body', 'Call me back please ')->count())->toBe(1);
});

test('a signed status callback with spaced values is accepted', function () {
    twilioWebhook($this, route('webhooks.communications.twilio.messaging.status'), [
        'MessageSid' => 'SMsigstatus1',
        'MessageStatus' => 'delivered',
        'ErrorCode' => '',
        'To' => '+17195551234',
    ])->assertNoContent();
});

test('a signed voice webhook with a spaced caller name and a query string is accepted', function () {
    twilioWebhook($this, route('webhooks.communications.twilio.voice.incoming'), [
        'CallSid' => 'CAsigvoice01',
        'From' => '+17195551234',
        'To' => '+17195550100',
        'Direction' => 'inbound',
        'CallStatus' => 'ringing',
        'CallerName' => 'SOARES EDWARD ',
    ])->assertOk();

    twilioWebhook($this, route('webhooks.communications.twilio.voice.recording', ['voicemail' => 1]), [
        'CallSid' => 'CAsigvoice01',
        'RecordingSid' => 'REsig01',
        'RecordingStatus' => 'completed',
        'RecordingUrl' => 'https://api.twilio.com/recordings/REsig01',
        'RecordingDuration' => '7',
    ])->assertSuccessful();
});

test('the form body parser keeps values exactly, including plus signs and spaces', function () {
    expect(TwilioWebhookVerifier::parseFormBody('Body=%20hi+there%20&From=%2B17195551234&Empty=&Flag'))
        ->toBe(['Body' => ' hi there ', 'From' => '+17195551234', 'Empty' => '', 'Flag' => '']);
});
