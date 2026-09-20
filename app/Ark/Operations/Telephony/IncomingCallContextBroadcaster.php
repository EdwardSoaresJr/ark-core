<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Communications\CommsInterruptBroadcast;
use App\Ark\Operations\Conversations\CustomerCallContext;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\Telephony\Events\IncomingCallReceived;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IncomingCallContextBroadcaster
{
    public function __construct(
        private readonly IncomingCallContextPresenter $presenter,
        private readonly CommsInterruptBroadcast $interruptBroadcast,
        private readonly CustomerCallContextResolver $callContextResolver,
    ) {}

    public static function cacheKey(): string
    {
        return 'telephony:last-incoming-call';
    }

    public function broadcast(CallSession $session, ?CustomerCallContext $context): void
    {
        if ($session->direction !== CallSessionDirection::Inbound) {
            return;
        }

        if (! $session->isActivelyLive()) {
            return;
        }

        $payload = $this->presenter->present($session, $context);

        Cache::put(self::cacheKey(), $payload, now()->addMinutes(2));

        if (! IncomingCallBroadcast::enabled()) {
            return;
        }

        $this->broadcastIncomingCall($payload);
        $this->safeInterruptShow('call', array_merge($payload, ['kind' => 'call']));
    }

    public function broadcastForParentCallSid(string $parentCallSid): void
    {
        $session = CallSession::query()
            ->with('customer')
            ->where('provider_call_sid', $parentCallSid)
            ->first();

        if ($session === null) {
            return;
        }

        $context = $session->customer_id !== null
            ? $this->callContextResolver->resolveForCustomer($session->customer)
            : $this->callContextResolver->resolve(
                app(InboundCallerDisplayPhone::class)->normalizedForSession($session) ?? $session->normalized_from
            );

        $this->broadcastUpdate($session, $context);
    }

    public function broadcastUpdate(CallSession $session, ?CustomerCallContext $context): void
    {
        $payload = $this->presenter->present($session, $context);
        $cached = Cache::get(self::cacheKey());
        $cachedMatches = is_array($cached) && (int) ($cached['call_session_id'] ?? 0) === $session->id;

        if (! $session->isActivelyLive()) {
            if ($cachedMatches) {
                Cache::forget(self::cacheKey());
            }

            if (IncomingCallBroadcast::enabled()) {
                $this->broadcastCallSessionUpdated($payload);
            }

            $this->safeInterruptClear(
                'call',
                CommsInterruptBroadcast::interruptKey('call', ['call_session_id' => $session->id]),
            );

            return;
        }

        if ($cachedMatches && $session->direction === CallSessionDirection::Inbound) {
            Cache::put(self::cacheKey(), $payload, now()->addMinutes(2));
        }

        if (! IncomingCallBroadcast::enabled()) {
            return;
        }

        $this->broadcastCallSessionUpdated($payload);

        if ($session->direction === CallSessionDirection::Inbound) {
            $this->safeInterruptUpdate('call', array_merge($payload, ['kind' => 'call']));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function broadcastIncomingCall(array $payload): void
    {
        $this->safeBroadcast(
            fn (): mixed => broadcast(new IncomingCallReceived($payload)),
            'incoming call',
            $payload['call_session_id'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function broadcastCallSessionUpdated(array $payload): void
    {
        $this->safeBroadcast(
            fn (): mixed => broadcast(new Events\CallSessionUpdated($payload)),
            'call session update',
            $payload['call_session_id'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $interrupt
     */
    private function safeInterruptShow(string $kind, array $interrupt): void
    {
        $this->safeBroadcast(
            fn (): mixed => $this->interruptBroadcast->show($kind, $interrupt),
            'comms interrupt show',
            $interrupt['call_session_id'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $interrupt
     */
    private function safeInterruptUpdate(string $kind, array $interrupt): void
    {
        $this->safeBroadcast(
            fn (): mixed => $this->interruptBroadcast->update($kind, $interrupt),
            'comms interrupt update',
            $interrupt['call_session_id'] ?? null,
        );
    }

    private function safeInterruptClear(string $kind, string $interruptKey): void
    {
        $this->safeBroadcast(
            fn (): mixed => $this->interruptBroadcast->clear($kind, $interruptKey),
            'comms interrupt clear',
            null,
        );
    }

    private function safeBroadcast(callable $callback, string $context, mixed $callSessionId): void
    {
        try {
            $callback();
        } catch (BroadcastException $exception) {
            $this->logBroadcastFailure($context, $callSessionId, $exception);
        } catch (\Throwable $exception) {
            if (! $this->isBroadcastFailure($exception)) {
                throw $exception;
            }

            $this->logBroadcastFailure($context, $callSessionId, $exception);
        }
    }

    private function isBroadcastFailure(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'pusher error')
            || str_contains($message, 'broadcast');
    }

    private function logBroadcastFailure(string $context, mixed $callSessionId, \Throwable $exception): void
    {
        Log::warning('Telephony broadcast failed; call flow continues.', [
            'context' => $context,
            'call_session_id' => $callSessionId,
            'message' => $exception->getMessage(),
        ]);
    }
}
