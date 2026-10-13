<?php

namespace App\Ark\Operations\Messaging;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Direct Twilio Messages API. Adapted from arksmsv2
 * App\Ark\Operations\Messaging\TwilioMessagingSender. Does not call ARK Platform.
 */
final class TwilioOutboundSmsTransport implements OutboundSmsTransport
{
    public function __construct(private readonly MessagingAccountResolver $accounts) {}

    public function isConfigured(): bool
    {
        return $this->accounts->current()?->complete() === true;
    }

    /**
     * @param  list<string>  $mediaUrls
     * @param  list<array{type: string, id: string}>  $context
     */
    public function send(string $toPhone, string $body, array $mediaUrls = [], array $context = []): OutboundSmsResult
    {
        $account = $this->accounts->current();
        if ($account === null || ! $account->complete()) {
            throw new RuntimeException('Outbound SMS is not configured.');
        }

        self::refuseLegacyPlatform($account->apiBase);

        $idempotencyKey = $this->idempotencyKey($context);
        $receiptKey = $idempotencyKey === null
            ? null
            : hash('sha256', $account->accountSid.'|'.$idempotencyKey);
        if ($receiptKey !== null) {
            $existing = MessagingSendReceipt::query()->where('receipt_key', $receiptKey)->first();
            if ($existing !== null) {
                return new OutboundSmsResult((string) $existing->provider_message_sid, 'accepted');
            }
        }

        $form = [
            'To' => $toPhone,
            'From' => $account->from,
            'Body' => $body,
        ];
        if ($mediaUrls !== []) {
            $form['MediaUrl'] = array_values($mediaUrls);
        }
        $callback = route('webhooks.twilio.messaging.status');
        if ($callback !== '') {
            $form['StatusCallback'] = $callback;
        }

        $url = rtrim($account->apiBase, '/').'/2010-04-01/Accounts/'.rawurlencode($account->accountSid).'/Messages.json';
        $response = Http::withBasicAuth($account->accountSid, $account->authToken)
            ->withBody(self::formBody($form), 'application/x-www-form-urlencoded')
            ->timeout(20)
            ->post($url);

        if (! $response->successful()) {
            throw new RuntimeException('Outbound SMS was rejected.');
        }

        $sid = trim((string) $response->json('sid'));
        if ($sid === '') {
            throw new RuntimeException('Outbound SMS was rejected.');
        }

        if ($receiptKey !== null) {
            try {
                MessagingSendReceipt::query()->create([
                    'receipt_key' => $receiptKey,
                    'provider_message_sid' => $sid,
                ]);
            } catch (QueryException) {
                $stored = MessagingSendReceipt::query()->where('receipt_key', $receiptKey)->value('provider_message_sid');
                if (is_string($stored) && $stored !== '') {
                    return new OutboundSmsResult($stored, 'accepted');
                }
            }
        }

        $status = trim((string) $response->json('status'));

        return new OutboundSmsResult($sid, $status !== '' ? $status : 'queued');
    }

    public static function refuseLegacyPlatform(string $apiBase): void
    {
        $host = strtolower((string) parse_url($apiBase, PHP_URL_HOST));
        if (in_array($host, [
            'cloud.arksms.com',
            'app.arksms.com',
            'api.arksms.com',
            'platform.autorepairkeeper.com',
        ], true)) {
            throw new RuntimeException('Outbound SMS does not use the legacy platform.');
        }
    }

    /**
     * @param  list<array{type: string, id: string}>  $context
     */
    private function idempotencyKey(array $context): ?string
    {
        foreach ($context as $item) {
            if (($item['type'] ?? '') === 'idempotency_key' && is_string($item['id'] ?? null) && $item['id'] !== '') {
                return $item['id'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private static function formBody(array $form): string
    {
        $pairs = [];
        foreach ($form as $key => $value) {
            if ($key === 'MediaUrl' && is_array($value)) {
                foreach ($value as $url) {
                    if (is_string($url) && $url !== '') {
                        $pairs[] = 'MediaUrl='.rawurlencode($url);
                    }
                }

                continue;
            }
            $pairs[] = rawurlencode((string) $key).'='.rawurlencode((string) $value);
        }

        return implode('&', $pairs);
    }
}
