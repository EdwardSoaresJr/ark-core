<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\ConversationMessage;
use Illuminate\Http\JsonResponse;

final class ConversationDeliveryJsonResponse
{
    public function __construct(
        private readonly ConversationMessagePresenter $presenter,
        private readonly ConversationMessageRenderer $renderer,
    ) {}

    /**
     * @param  list<ConversationMessage>  $messages
     * @param  array<string, mixed>  $extra
     */
    public function make(array $messages, array $extra = []): JsonResponse
    {
        $deliveries = collect($messages)
            ->map(function (ConversationMessage $message): array {
                return [
                    'message_id' => $message->id,
                    'channel' => $message->channel->value,
                    'message' => $this->presenter->present($message),
                    'html' => $this->renderer->render($message, 'border-t border-slate-100'),
                ];
            })
            ->values()
            ->all();

        $primary = $deliveries[0] ?? null;

        return response()->json(array_merge([
            'deliveries' => $deliveries,
            'message_id' => $primary['message_id'] ?? null,
            'message' => $primary['message'] ?? null,
            'html' => $primary['html'] ?? null,
        ], $extra));
    }
}
