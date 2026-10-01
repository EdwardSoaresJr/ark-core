<?php

namespace App\Ark\Mobile\Push;

use App\Ark\Operations\Messaging\Events\ConversationMessageReceived;
use App\Ark\Operations\Observations\OperationalObservationType;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

final class DispatchMobilePushForInboundMessage implements ShouldQueue
{
    public function __construct(
        private readonly MobilePushService $mobilePush,
        private readonly MobileAwarePushCopy $pushCopy,
    ) {}

    public function handle(ConversationMessageReceived $event): void
    {
        if (! $this->mobilePush->isEnabled()) {
            return;
        }

        $interrupt = $event->payload['interrupt'] ?? null;

        if (! is_array($interrupt)) {
            return;
        }

        if (($interrupt['direction'] ?? null) !== 'inbound') {
            return;
        }

        $copy = $this->pushCopy->forInboundMessageInterrupt($interrupt);
        $conversationId = isset($interrupt['conversation_id']) ? (int) $interrupt['conversation_id'] : null;
        $repairOrderId = null;

        if (isset($interrupt['open_repair_order_ids'][0])) {
            $repairOrderId = (int) $interrupt['open_repair_order_ids'][0];
        }

        $message = new MobilePushMessage(
            title: $copy['title'],
            body: $copy['body'],
            deepLink: 'conversation',
            repairOrderId: $repairOrderId,
            conversationId: $conversationId,
            tone: OperationalObservationType::CustomerReplied->tone(),
        );

        $staff = User::query()
            ->active()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                ArkRole::Admin->value,
                ArkRole::Advisor->value,
            ]))
            ->get()
            ->all();

        $this->mobilePush->sendToUsers($message, $staff);
    }
}
