<?php

namespace App\Console\Commands;

use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Telephony\MobileVoice\EnsureTwilioMobileVoiceCredentialsAction;
use App\Ark\Operations\Telephony\MobileVoice\MobileVoiceCredentials;
use Illuminate\Console\Command;

class EnsureTwilioMobileVoiceCommand extends Command
{
    protected $signature = 'ark:telephony:ensure-mobile-voice {--force : Recreate API key and credentials even when partially configured}';

    protected $description = 'Provision Twilio Voice Client credentials (API Key, TwiML App, FCM) for ARK Phone in-app calling';

    public function handle(EnsureTwilioMobileVoiceCredentialsAction $action): int
    {
        if (! ShopIntegrationCredentials::forCurrentShop()->twilioConfigured()) {
            $this->error('Twilio account SID and auth token are required in shop settings.');

            return self::FAILURE;
        }

        $before = MobileVoiceCredentials::forCurrentShop()->twilioClientConfigured();

        try {
            $result = $action->execute($this->option('force'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $after = MobileVoiceCredentials::forCurrentShop()->twilioClientConfigured();

        if ($result['created'] !== []) {
            $this->info('Created: '.implode(', ', $result['created']));
        }

        if ($result['reused'] !== []) {
            $this->line('Reused: '.implode(', ', $result['reused']));
        }

        if (filled($result['api_key_sid'])) {
            $this->line('  API Key SID: '.$result['api_key_sid']);
        }

        if (filled($result['twiml_app_sid'])) {
            $this->line('  TwiML App SID: '.$result['twiml_app_sid']);
        }

        if (filled($result['fcm_credential_sid'])) {
            $this->line('  FCM Credential SID: '.$result['fcm_credential_sid']);
        } else {
            $this->warn('FCM credential not provisioned — inbound wake on Android may wait until Firebase service account is mounted.');
        }

        if ($after) {
            $this->info('Twilio mobile voice client is ready for in-app calling.');

            return self::SUCCESS;
        }

        if ($before) {
            $this->warn('Twilio mobile voice client was ready before this run but is incomplete now. Re-run with saved secrets or create a new API key.');

            return self::FAILURE;
        }

        $this->warn('Twilio mobile voice client is still incomplete. Save API key secret manually if an existing key was reused without a stored secret.');

        return self::FAILURE;
    }
}
