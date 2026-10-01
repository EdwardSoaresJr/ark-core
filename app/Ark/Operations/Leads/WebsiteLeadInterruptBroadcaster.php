<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Communications\CommsInterruptBroadcast;
use App\Ark\Operations\Messaging\ConversationBroadcast;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class WebsiteLeadInterruptBroadcaster
{
    public function __construct(
        private readonly WebsiteLeadInterruptPresenter $presenter,
        private readonly CommsInterruptBroadcast $interruptBroadcast,
    ) {}

    public static function cacheKey(): string
    {
        return 'website-lead:last-interrupt';
    }

    public function broadcast(Lead $lead): void
    {
        if (! ConversationBroadcast::enabled()) {
            return;
        }

        if ($lead->source !== LeadSource::Website || $lead->state === LeadState::Spam || ! $lead->isNotContacted()) {
            return;
        }

        $payload = $this->presenter->forLead($lead);
        Cache::put(self::cacheKey(), $payload, now()->addHours(8));

        try {
            $this->interruptBroadcast->show('website_lead', $payload);
        } catch (BroadcastException $exception) {
            Log::warning('Website lead interrupt broadcast failed.', [
                'lead_id' => $lead->id,
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            if (! $this->isBroadcastFailure($exception)) {
                throw $exception;
            }

            Log::warning('Website lead interrupt broadcast failed.', [
                'lead_id' => $lead->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function clearForLead(int $leadId): void
    {
        $cached = Cache::get(self::cacheKey());

        if (! is_array($cached) || (int) ($cached['lead_id'] ?? 0) !== $leadId) {
            return;
        }

        Cache::forget(self::cacheKey());
    }

    private function isBroadcastFailure(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'pusher error')
            || str_contains($message, 'broadcast');
    }
}
