<?php

namespace App\Ark\Platform\Http;

use App\Ark\Operations\Communications\CommsInterruptBroadcast;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\InboundConversationPayload;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\Messaging\InboundSmsConversationIngress;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Telephony\Events\CallSessionUpdated;
use App\Ark\Operations\Telephony\Events\IncomingCallReceived;
use App\Ark\Install\InstallationIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Platform-signed fabric events → Core operational authority + Reverb.
 */
final class FabricIngressController
{
    public function __construct(
        private readonly CommsInterruptBroadcast $interruptBroadcast,
        private readonly InboundSmsConversationIngress $smsIngress,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'operation' => ['required', 'string', 'max:80'],
            'installation_id' => ['required', 'uuid'],
            'occurred_at' => ['nullable', 'string', 'max:64'],
            'payload' => ['nullable', 'array'],
        ]);

        if (! hash_equals(InstallationIdentity::uuid(), (string) $data['installation_id'])) {
            abort(401, 'Installation mismatch.');
        }

        /** @var array<string, mixed> $payload */
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

        return match ((string) $data['operation']) {
            'voice.incoming.started' => $this->voiceIncomingStarted($payload),
            'voice.incoming.ended' => $this->voiceIncomingEnded($payload),
            'sms.incoming.received' => $this->smsIncomingReceived($payload),
            'sms.conversation.updated' => $this->smsConversationUpdated($payload),
            'payments.capture.updated' => $this->paymentsCaptureUpdated($payload),
            default => response()->json(['ok' => false, 'error' => 'unknown_operation'], 422),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function paymentsCaptureUpdated(array $payload): JsonResponse
    {
        $attemptId = (string) ($payload['capture_attempt_public_id'] ?? '');
        $idempotencyKey = (string) ($payload['idempotency_key'] ?? '');

        $attempt = null;
        if ($attemptId !== '') {
            $attempt = \App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt::query()
                ->where('public_id', $attemptId)
                ->first();
        }
        if ($attempt === null && $idempotencyKey !== '') {
            $attempt = \App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
        }

        if ($attempt === null) {
            Log::warning('ark_payments.fabric.attempt_not_found', [
                'capture_attempt_public_id' => $attemptId,
                'idempotency_key' => $idempotencyKey,
            ]);

            return response()->json(['ok' => true, 'applied' => false]);
        }

        app(\App\Ark\Operations\Payments\Capture\ApplyPaymentCaptureResultAction::class)
            ->apply($attempt, $payload);

        return response()->json(['ok' => true, 'applied' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function voiceIncomingStarted(array $payload): JsonResponse
    {
        $interrupt = $this->callInterrupt($payload);
        IncomingCallReceived::dispatch($interrupt);
        $this->interruptBroadcast->show('call', $interrupt);

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function voiceIncomingEnded(array $payload): JsonResponse
    {
        $interrupt = $this->callInterrupt($payload);
        $this->interruptBroadcast->clear(
            'call',
            CommsInterruptBroadcast::interruptKey('call', $interrupt),
        );
        CallSessionUpdated::dispatch(array_merge($interrupt, [
            'is_actively_live' => false,
        ]));

        return response()->json(['ok' => true]);
    }

    /**
     * Platform inbound text → ConversationMessage authority, then interrupt projection.
     *
     * @param  array<string, mixed>  $payload
     */
    private function smsIncomingReceived(array $payload): JsonResponse
    {
        $fromPhone = (string) ($payload['from_phone'] ?? '');
        $toPhone = (string) ($payload['to_phone'] ?? '');
        $body = (string) ($payload['body'] ?? '');
        $providerMessageId = (string) ($payload['provider_message_id'] ?? '');
        $optOut = (bool) ($payload['opt_out'] ?? false);

        $contactKey = PhoneNumber::normalize($fromPhone);
        if ($contactKey === null || $providerMessageId === '') {
            Log::warning('ark_texting.fabric.inbound_invalid', [
                'has_from' => $fromPhone !== '',
                'has_provider_id' => $providerMessageId !== '',
            ]);

            return response()->json(['ok' => false, 'error' => 'invalid_sms_payload'], 422);
        }

        $ingressPayload = new InboundConversationPayload(
            contactSurface: ConversationContactSurface::Phone,
            contactKey: $contactKey,
            providerMessageId: $providerMessageId,
            channel: OperationalCommunicationChannel::Sms,
            body: $body,
            media: [],
            metadata: array_filter([
                'to_number' => $toPhone !== '' ? $toPhone : null,
                'source' => 'ark_platform_texting',
                'opt_out' => $optOut ? true : null,
            ], fn (mixed $v): bool => $v !== null),
        );

        $result = $this->smsIngress->ingest($ingressPayload);
        $message = $result['message'];

        if ($optOut && $result['context']?->customer) {
            $this->markCustomerOptedOut($result['context']->customer);
        }

        if ($message === null) {
            return response()->json(['ok' => true, 'ingested' => false]);
        }

        $snippet = trim($body);
        if (mb_strlen($snippet) > 120) {
            $snippet = mb_substr($snippet, 0, 117).'…';
        }

        $interrupt = [
            'kind' => 'sms',
            'conversation_message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'snippet' => $snippet !== '' ? $snippet : '(text message)',
            'display_phone' => PhoneNumber::display($fromPhone) ?? $fromPhone,
            'customer_id' => $result['context']?->customer?->id,
            'customer_name' => $result['context']?->customer?->name,
        ];

        $this->interruptBroadcast->show('sms', $interrupt);

        return response()->json([
            'ok' => true,
            'ingested' => $result['created'],
            'conversation_message_id' => $message->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function smsConversationUpdated(array $payload): JsonResponse
    {
        if (isset($payload['from_phone'], $payload['provider_message_id'])) {
            return $this->smsIncomingReceived($payload);
        }

        $interrupt = $this->smsInterrupt($payload);
        $kind = (string) $interrupt['kind'];
        $this->interruptBroadcast->update($kind, $interrupt);

        return response()->json(['ok' => true]);
    }

    private function markCustomerOptedOut(Customer $customer): void
    {
        if ($customer->sms_consent_status === CustomerSmsConsentStatus::OptedOut) {
            return;
        }

        $customer->forceFill([
            'sms_consent_status' => CustomerSmsConsentStatus::OptedOut,
            'sms_consent_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function callInterrupt(array $payload): array
    {
        $interrupt = $this->interruptProjection($payload);
        $interrupt['kind'] = 'call';

        if (! array_key_exists('call_session_id', $interrupt) || ! filled($interrupt['display_phone'] ?? null)) {
            abort(422, 'Call interrupt requires call_session_id and display_phone.');
        }

        return $interrupt;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function smsInterrupt(array $payload): array
    {
        $interrupt = $this->interruptProjection($payload);
        $kind = (string) ($interrupt['kind'] ?? 'sms');
        if (! in_array($kind, ['sms', 'mms'], true)) {
            abort(422, 'SMS interrupt kind must be sms or mms.');
        }
        $interrupt['kind'] = $kind;

        if (! array_key_exists('conversation_message_id', $interrupt) || ! array_key_exists('snippet', $interrupt)) {
            abort(422, 'SMS interrupt requires conversation_message_id and snippet.');
        }

        return $interrupt;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function interruptProjection(array $payload): array
    {
        $interrupt = $payload['interrupt'] ?? $payload['context'] ?? $payload;

        if (! is_array($interrupt)) {
            abort(422, 'Invalid interrupt payload.');
        }

        /** @var array<string, mixed> $interrupt */
        return $interrupt;
    }
}
