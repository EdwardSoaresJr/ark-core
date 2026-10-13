<?php

namespace App\Ark\Operations\Messaging;

use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Standalone shop Twilio account. The auth token is encrypted at rest.
 * This does not restore the removed shop_settings credential columns.
 */
final class MessagingCredentialStore
{
    public function current(): ?MessagingAccount
    {
        if (! Schema::hasTable('messaging_provider_credentials')) {
            return null;
        }

        $row = MessagingProviderCredential::query()->where('provider', 'twilio')->first();
        if ($row === null) {
            return null;
        }

        $account = new MessagingAccount(
            (string) $row->account_sid,
            (string) $row->auth_token,
            (string) $row->from_number,
            (string) $row->api_base,
        );

        return $account->complete() ? $account : null;
    }

    public function save(string $accountSid, string $authToken, string $from, string $apiBase = 'https://api.twilio.com'): void
    {
        $accountSid = trim($accountSid);
        $authToken = trim($authToken);
        $from = trim($from);
        $apiBase = rtrim(trim($apiBase), '/');
        if ($accountSid === '' || $authToken === '' || $from === '' || $apiBase === '') {
            throw new RuntimeException('Texting credentials are incomplete.');
        }

        TwilioOutboundSmsTransport::refuseLegacyPlatform($apiBase);

        MessagingProviderCredential::query()->updateOrCreate(
            ['provider' => 'twilio'],
            [
                'account_sid' => $accountSid,
                'auth_token' => $authToken,
                'from_number' => $from,
                'api_base' => $apiBase,
            ],
        );
    }

    public function forget(): void
    {
        if (! Schema::hasTable('messaging_provider_credentials')) {
            return;
        }

        MessagingProviderCredential::query()->where('provider', 'twilio')->delete();
    }
}
