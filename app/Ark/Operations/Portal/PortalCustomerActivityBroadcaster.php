<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Communications\CommsInterruptBroadcast;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Messaging\ConversationBroadcast;
use App\Ark\Operations\Payments\PaymentCaptureSurface;
use App\Ark\Operations\Payments\PaymentGatewayAttempt;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class PortalCustomerActivityBroadcaster
{
    public function __construct(
        private readonly PortalCustomerActivityInterruptPresenter $presenter,
        private readonly CommsInterruptBroadcast $interruptBroadcast,
    ) {}

    public static function cacheKey(): string
    {
        return 'portal:last-customer-activity-interrupt';
    }

    public function broadcastEstimateView(RepairOrder $repairOrder, ConversationMessage $message): void
    {
        if (! ConversationBroadcast::enabled()) {
            return;
        }

        $payload = $this->presenter->forEstimateView($repairOrder, $message);
        $this->dispatch($payload);
    }

    public function broadcastPayment(RepairOrder $repairOrder, PaymentGatewayAttempt $attempt): void
    {
        if (! ConversationBroadcast::enabled()) {
            return;
        }

        if (! in_array($attempt->capture_surface, [
            PaymentCaptureSurface::Portal,
            PaymentCaptureSurface::PortalEstimateDeposit,
            PaymentCaptureSurface::PortalDepositRequest,
        ], true)) {
            return;
        }

        $payload = $this->presenter->forPayment($repairOrder, $attempt);
        $this->dispatch($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function clear(?string $portalInterruptKey = null): void
    {
        $cached = Cache::get(self::cacheKey());

        if (! is_array($cached)) {
            return;
        }

        if ($portalInterruptKey !== null && ($cached['portal_interrupt_key'] ?? '') !== $portalInterruptKey) {
            return;
        }

        $this->clearCached($cached);
    }

    public function clearForConversation(int $conversationId): void
    {
        if ($conversationId <= 0) {
            return;
        }

        $cached = Cache::get(self::cacheKey());

        if (! is_array($cached) || (int) ($cached['conversation_id'] ?? 0) !== $conversationId) {
            return;
        }

        $this->clearCached($cached);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatch(array $payload): void
    {
        Cache::put(self::cacheKey(), $payload, now()->addMinutes(5));

        try {
            $this->interruptBroadcast->show('portal', $payload);
        } catch (BroadcastException $exception) {
            Log::warning('Portal customer activity interrupt broadcast failed.', [
                'portal_interrupt_key' => $payload['portal_interrupt_key'] ?? null,
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            if (! $this->isBroadcastFailure($exception)) {
                throw $exception;
            }

            Log::warning('Portal customer activity interrupt broadcast failed.', [
                'portal_interrupt_key' => $payload['portal_interrupt_key'] ?? null,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    private function clearCached(array $cached): void
    {
        Cache::forget(self::cacheKey());

        try {
            $this->interruptBroadcast->clear(
                'portal',
                CommsInterruptBroadcast::interruptKey('portal', $cached),
            );
        } catch (BroadcastException $exception) {
            Log::warning('Portal customer activity interrupt clear broadcast failed.', [
                'portal_interrupt_key' => $cached['portal_interrupt_key'] ?? null,
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            if (! $this->isBroadcastFailure($exception)) {
                throw $exception;
            }

            Log::warning('Portal customer activity interrupt clear broadcast failed.', [
                'portal_interrupt_key' => $cached['portal_interrupt_key'] ?? null,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function isBroadcastFailure(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'pusher error')
            || str_contains($message, 'broadcast');
    }
}
