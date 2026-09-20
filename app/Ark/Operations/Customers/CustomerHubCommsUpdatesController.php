<?php

namespace App\Ark\Operations\Customers;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationTimeline;
use App\Ark\Operations\Messaging\ConversationMessageRenderer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Platform\Communications\ManagedCommunicationsGate;
use App\Ark\Platform\Communications\PlatformSmsTimelineProjection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

final class CustomerHubCommsUpdatesController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        ConversationTimeline $conversationTimeline,
        CustomerHubCommsTimeline $hubCommsTimeline,
        ConversationMessageRenderer $renderer,
        PlatformSmsTimelineProjection $platformSms,
    ): JsonResponse {
        $sinceMessageId = max(0, (int) $request->query('since_message_id', 0));
        $sinceOccurredAt = $this->sinceOccurredAt($request);

        $messages = $conversationTimeline->forCustomerRelationshipSince(
            $customer,
            $sinceMessageId,
            PhoneNumber::normalize($customer->phone),
        );

        $coreItems = $messages
            ->map(fn (ConversationMessage $message): array => [
                'message_id' => $message->id,
                'platform_message_id' => null,
                'occurred_at' => $message->occurred_at?->utc()->toIso8601String(),
                'filter' => $hubCommsTimeline->filterForMessage($message),
                'html' => $renderer->render($message),
            ]);

        $items = $coreItems
            ->concat($this->platformItems($customer, $sinceOccurredAt, $platformSms))
            ->sortBy(fn (array $item): string => (string) ($item['occurred_at'] ?? ''))
            ->values();

        return response()
            ->json(['items' => $items])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    private function sinceOccurredAt(Request $request): ?Carbon
    {
        $raw = trim((string) $request->query('since_occurred_at', ''));

        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function platformItems(
        Customer $customer,
        ?Carbon $sinceOccurredAt,
        PlatformSmsTimelineProjection $platformSms,
    ): Collection {
        if (! ManagedCommunicationsGate::platformInbox() || $sinceOccurredAt === null) {
            return collect();
        }

        return $platformSms
            ->forCustomer($customer, PhoneNumber::normalize($customer->phone), 50)
            ->filter(fn ($entry): bool => $entry->occurredAt->gt($sinceOccurredAt))
            ->map(fn ($entry): array => [
                'message_id' => null,
                'platform_message_id' => $entry->metadata['platform_message_public_id'] ?? null,
                'occurred_at' => $entry->occurredAt->utc()->toIso8601String(),
                'filter' => $entry->hubFilter(),
                'html' => view('operations.timeline.partials.hub-event-row', ['event' => $entry])->render(),
            ])
            ->values();
    }
}
