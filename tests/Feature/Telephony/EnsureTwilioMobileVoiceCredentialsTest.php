<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\MobileVoice\EnsureTwilioMobileVoiceCredentialsAction;
use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceCredentials;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    ShopSettings::current()->update([
        'telephony_provider' => TelephonyProviderType::Twilio->value,
        'twilio_account_sid' => 'AC-test-account',
        'twilio_auth_token' => 'test-auth-token',
        'twilio_api_key_sid' => null,
        'twilio_api_key_secret' => null,
        'twilio_voice_twiml_app_sid' => null,
        'twilio_fcm_credential_sid' => null,
    ]);
});

test('ensure twilio mobile voice credentials provisions api key and twiml app', function (): void {
    Http::fake([
        'api.twilio.com/2010-04-01/Accounts/AC-test-account/Keys.json' => Http::sequence()
            ->push(['keys' => []])
            ->push(['sid' => 'SK-created', 'secret' => 'secret-created']),
        'api.twilio.com/2010-04-01/Accounts/AC-test-account/Applications.json' => Http::sequence()
            ->push(['applications' => []])
            ->push(['sid' => 'AP-created']),
        'notify.twilio.com/v1/Credentials' => Http::sequence()
            ->push(['credentials' => []])
            ->push(['sid' => 'CR-created']),
    ]);

    $result = app(EnsureTwilioMobileVoiceCredentialsAction::class)->execute();

    expect($result['created'])->toContain('api_key')
        ->and($result['created'])->toContain('twiml_app')
        ->and(MobileVoiceCredentials::forCurrentShop()->twilioClientConfigured())->toBeTrue();

    $settings = ShopSettings::current()->fresh();
    expect($settings->twilio_api_key_sid)->toBe('SK-created')
        ->and($settings->twilio_voice_twiml_app_sid)->toBe('AP-created');
});
