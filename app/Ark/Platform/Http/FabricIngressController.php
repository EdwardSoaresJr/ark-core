<?php

namespace App\Ark\Platform\Http;

use App\Ark\Install\InstallationIdentity;
use App\Ark\Mobile\Push\NotifyMobileLifecyclePushAction;
use App\Ark\Operations\Communications\CommsInterruptBroadcast;
use App\Ark\Operations\Communications\HostedSmsInterrupt;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\InboundConversationPayload;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsConsentStatus;
use App\Ark\Operations\Messaging\InboundSmsConversationIngress;
use App\Ark\Operations\Payments\ApplyPaymentGatewayCaptureResultAction;
use App\Ark\Operations\Payments\Capture\ApplyPaymentCaptureResultAction;
use App\Ark\Operations\Payments\Capture\PaymentCaptureAttempt;
use App\Ark\Operations\Payments\PaymentGatewayAttempt;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionMediaCaptureStatus;
use App\Ark\Operations\Telephony\CallSessionRecorder;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\IncomingCallContextBroadcaster;
use App\Ark\Operations\Telephony\IncomingCallPayload;
use App\Ark\Operations\Telephony\Media\CallSessionMediaMetadata;
use App\Ark\Operations\Telephony\ProcessIncomingCallAction;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Platform\Communications\ManagedCommunicationsGate;
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
        private readonly ProcessIncomingCallAction $incomingCalls,
        private readonly CallSessionRecorder $callSessions,
        private readonly IncomingCallContextBroadcaster $callBroadcaster,
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
            'voice.recording.available' => $this->voiceRecordingAvailable($payload, voicemail: false),
            'voice.voicemail.available' => $this->voiceRecordingAvailable($payload, voicemail: true),
            'sms.incoming.received' => $this->smsIncomingReceived($payload),
            'sms.conversation.updated' => $this->smsConversationUpdated($payload),
            'sms.delivery.updated' => $this->smsDeliveryUpdated($payload),
            'payments.capture.updated' => $this->paymentsCaptureUpdated($payload),
            default => response()->json(['ok' => false, 'error' => 'unknown_operation'], 422),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function voiceRecordingAvailable(array $payload, bool $voicemail): JsonResponse
    {
        $callSid = (string) ($payload['provider_call_sid'] ?? '');
        $recordingUrl = (string) ($payload['recording_url'] ?? '');
        $recordingSid = (string) ($payload['recording_sid'] ?? '');
        $duration = (int) ($payload['duration_seconds'] ?? 0);

        if ($callSid === '' || $recordingUrl === '') {
            return response()->json(['ok' => false, 'error' => 'invalid_recording_payload'], 422);
        }

        $session = CallSession::query()
            ->where('provider_call_sid', $callSid)
            ->first();

        if ($session === null) {
            Log::warning('ark_voice.fabric.recording_session_missing', [
                'provider_call_sid' => $callSid,
                'voicemail' => $voicemail,
            ]);

            return response()->json(['ok' => true, 'applied' => false]);
        }

        $metadata = CallSessionMediaMetadata::forTwilioWebhook(
            $recordingUrl,
            $duration,
            $recordingSid !== '' ? $recordingSid : null,
        );

        if ($voicemail) {
            $session->forceFill([
                'voicemail_url' => $recordingUrl,
                'voicemail_sid' => $recordingSid !== '' ? $recordingSid : null,
                'voicemail_duration_seconds' => $duration > 0 ? $duration : null,
                'voicemail_capture_status' => CallSessionMediaCaptureStatus::Available,
                'voicemail_capture_error' => null,
                'voicemail_media_metadata' => $metadata,
            ])->saveQuietly();

            app(NotifyMobileLifecyclePushAction::class)->forVoicemail($session->fresh());
        } else {
            $session->forceFill([
                'recording_url' => $recordingUrl,
                'recording_sid' => $recordingSid !== '' ? $recordingSid : null,
                'recording_duration_seconds' => $duration > 0 ? $duration : null,
                'recording_capture_status' => CallSessionMediaCaptureStatus::Available,
                'recording_capture_error' => null,
                'recording_media_metadata' => $metadata,
            ])->saveQuietly();
        }

        return response()->json(['ok' => true, 'applied' => true, 'call_session_id' => $session->id]);
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
            $attempt = PaymentCaptureAttempt::query()
                ->where('public_id', $attemptId)
                ->first();
        }
        if ($attempt === null && $idempotencyKey !== '') {
            $attempt = PaymentCaptureAttempt::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
        }

        if ($attempt !== null) {
            app(ApplyPaymentCaptureResultAction::class)
                ->apply($attempt, $payload);

            return response()->json(['ok' => true, 'applied' => true]);
        }

        $gatewayAttempt = null;
        if ($attemptId !== '') {
            $gatewayAttempt = PaymentGatewayAttempt::query()
                ->where('public_id', $attemptId)
                ->first();
        }
        if ($gatewayAttempt === null && $idempotencyKey !== '') {
            $gatewayAttempt = PaymentGatewayAttempt::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
        }

        if ($gatewayAttempt === null) {
            Log::warning('ark_payments.fabric.attempt_not_found', [
                'capture_attempt_public_id' => $attemptId,
                'idempotency_key' => $idempotencyKey,
            ]);

            return response()->json(['ok' => true, 'applied' => false]);
        }

        app(ApplyPaymentGatewayCaptureResultAction::class)
            ->apply($gatewayAttempt, $payload);

        return response()->json(['ok' => true, 'applied' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function voiceIncomingStarted(array $payload): JsonResponse
    {
        $callPayload = $this->incomingCallPayloadFromFabric($payload, preferStatus: CallSessionStatus::Ringing);
        if ($callPayload === null) {
            Log::warning('ark_voice.fabric.inbound_invalid', [
                'keys' => array_keys($payload),
            ]);

            return response()->json(['ok' => false, 'error' => 'invalid_voice_payload'], 422);
        }

        $result = $this->incomingCalls->execute($callPayload);

        return response()->json([
            'ok' => true,
            'ingested' => $result['created'],
            'call_session_id' => $result['session']?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function voiceIncomingEnded(array $payload): JsonResponse
    {
        $status = CallSessionStatus::fromTwilioStatus((string) ($payload['call_status'] ?? 'completed'));
        if ($status === CallSessionStatus::Ringing) {
            $status = CallSessionStatus::Completed;
        }

        $callPayload = $this->incomingCallPayloadFromFabric($payload, preferStatus: $status);
        if ($callPayload === null) {
            return response()->json(['ok' => false, 'error' => 'invalid_voice_payload'], 422);
        }

        $updated = $this->callSessions->updateStatus($callPayload);
        if ($updated === null) {
            // Late status with no prior started event — still record for Calls history.
            [$session] = $this->callSessions->record($callPayload);
            $this->callBroadcaster->broadcastUpdate($session, null);

            return response()->json(['ok' => true, 'call_session_id' => $session->id]);
        }

        [$session] = $updated;
        $this->callBroadcaster->broadcastUpdate($session, null);

        return response()->json(['ok' => true, 'call_session_id' => $session->id]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function incomingCallPayloadFromFabric(array $payload, CallSessionStatus $preferStatus): ?IncomingCallPayload
    {
        $providerCallSid = (string) ($payload['provider_call_sid'] ?? $payload['CallSid'] ?? '');
        $fromPhone = (string) ($payload['from_phone'] ?? $payload['From'] ?? '');
        $toPhone = (string) ($payload['to_phone'] ?? $payload['To'] ?? '');

        if ($providerCallSid === '' || $fromPhone === '') {
            // Legacy interrupt-shaped payloads
            if (array_key_exists('call_session_id', $payload) && filled($payload['display_phone'] ?? null)) {
                return null; // handled by legacy path — keep null to force callers to use callInterrupt
            }

            return null;
        }

        $normalizedFrom = PhoneNumber::normalize($fromPhone) ?? preg_replace('/\D+/', '', $fromPhone) ?? '';
        $normalizedTo = PhoneNumber::normalize($toPhone);

        return new IncomingCallPayload(
            provider: TelephonyProviderType::Twilio,
            providerCallSid: $providerCallSid,
            fromNumber: $fromPhone,
            toNumber: $toPhone,
            normalizedFrom: (string) $normalizedFrom,
            normalizedTo: $normalizedTo,
            status: $preferStatus,
            rawPayload: $payload,
        );
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
            media: $this->normalizeFabricMedia($payload['media'] ?? []),
            metadata: array_filter([
                'to_number' => $toPhone !== '' ? $toPhone : null,
                'source' => 'ark_platform_texting',
                'opt_out' => $optOut ? true : null,
                'message_public_id' => $payload['message_public_id'] ?? null,
                'conversation_public_id' => $payload['conversation_public_id'] ?? null,
            ], fn (mixed $v): bool => $v !== null),
        );

        $result = $this->smsIngress->ingest($ingressPayload);
        $message = $result['message'];
        $conversation = $result['conversation'] ?? $message?->conversation;

        if ($optOut && $result['context']?->customer) {
            $this->markCustomerOptedOut($result['context']->customer);
        }

        if ($message !== null) {
            return response()->json([
                'ok' => true,
                'ingested' => $result['created'],
                'conversation_message_id' => $message->id,
            ]);
        }

        $interrupt = HostedSmsInterrupt::fromPlatformInbound(
            payload: $payload,
            fromPhone: $fromPhone,
            body: $body,
            hasMedia: $ingressPayload->media !== [],
            conversationId: $conversation?->id,
            customerId: $result['context']?->customer?->id,
            customerName: $result['context']?->customer?->name,
        );

        if ($interrupt === null) {
            $reason = HostedSmsInterrupt::publicId($payload['message_public_id'] ?? null) === null
                ? 'missing_message_identity'
                : 'missing_conversation_identity';

            Log::warning('hosted_sms.interrupt_rejected', [
                'reason' => $reason,
                'has_platform_message_id' => HostedSmsInterrupt::publicId($payload['message_public_id'] ?? null) !== null,
                'has_conversation_id' => $conversation !== null,
                'has_platform_conversation_id' => HostedSmsInterrupt::publicId($payload['conversation_public_id'] ?? null) !== null,
            ]);

            return response()->json([
                'ok' => true,
                'ingested' => false,
                'mirrored' => false,
                'interrupt' => false,
                'reason' => $reason,
            ]);
        }

        $this->interruptBroadcast->show((string) $interrupt['kind'], $interrupt);

        return response()->json([
            'ok' => true,
            'ingested' => false,
            'mirrored' => false,
            'interrupt' => true,
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function smsDeliveryUpdated(array $payload): JsonResponse
    {
        if (! ManagedCommunicationsGate::coreMirrorEnabled()) {
            return response()->json(['ok' => true, 'applied' => false, 'reason' => 'core_mirror_off']);
        }

        $providerMessageId = (string) ($payload['provider_message_id'] ?? '');
        $deliveryStatus = (string) ($payload['delivery_status'] ?? '');

        if ($providerMessageId === '' || $deliveryStatus === '') {
            return response()->json(['ok' => false, 'error' => 'invalid_delivery_payload'], 422);
        }

        $message = ConversationMessage::query()
            ->where(function ($query) use ($providerMessageId): void {
                $query->where('metadata->provider_message_id', $providerMessageId)
                    ->orWhere('metadata->twilio_message_sid', $providerMessageId);
            })
            ->first();

        if ($message === null) {
            return response()->json(['ok' => true, 'applied' => false]);
        }

        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $metadata['delivery_status'] = $deliveryStatus;
        $metadata['platform_delivery_updated_at'] = now()->toIso8601String();
        $message->forceFill(['metadata' => $metadata])->saveQuietly();

        return response()->json(['ok' => true, 'applied' => true, 'conversation_message_id' => $message->id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeFabricMedia(mixed $media): array
    {
        if (! is_array($media)) {
            return [];
        }

        $out = [];
        foreach ($media as $item) {
            if (! is_array($item)) {
                continue;
            }
            $url = trim((string) ($item['url'] ?? $item['provider_url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $out[] = [
                'url' => $url,
                'content_type' => (string) ($item['content_type'] ?? 'application/octet-stream'),
                'provider_media_sid' => $item['provider_media_sid'] ?? null,
                'byte_size' => $item['byte_size'] ?? null,
            ];
        }

        return $out;
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
