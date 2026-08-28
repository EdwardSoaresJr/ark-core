<?php

namespace App\Ark\Operations\Customers;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationTimeline;
use App\Ark\Operations\Messaging\ConversationMessageRenderer;
use App\Ark\Operations\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerHubCommsUpdatesController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        ConversationTimeline $conversationTimeline,
        CustomerHubCommsTimeline $hubCommsTimeline,
        ConversationMessageRenderer $renderer,
    ): JsonResponse {
        $sinceMessageId = max(0, (int) $request->query('since_message_id', 0));

        $messages = $conversationTimeline->forCustomerRelationshipSince(
            $customer,
            $sinceMessageId,
            PhoneNumber::normalize($customer->phone),
        );

        $items = $messages
            ->map(fn (ConversationMessage $message): array => [
                'message_id' => $message->id,
                'filter' => $hubCommsTimeline->filterForMessage($message),
                'html' => $renderer->render($message),
            ])
            ->values();

        return response()
            ->json(['items' => $items])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
