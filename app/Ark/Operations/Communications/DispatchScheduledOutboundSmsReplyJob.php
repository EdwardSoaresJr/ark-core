<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Messaging\SendOutboundMessageAction;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Owns execution only. The scheduled row never sends.
 */
class DispatchScheduledOutboundSmsReplyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $scheduledOutboundMessageId,
    ) {}

    public function handle(
        SendOutboundMessageAction $send,
        CancelScheduledOutboundMessagesAction $cancel,
        ConversationResolver $conversations,
    ): void {
        $row = ScheduledOutboundMessage::query()->find($this->scheduledOutboundMessageId);

        if ($row === null || ! $row->isScheduled()) {
            return;
        }

        if ($row->type !== ScheduledOutboundMessageType::SmsReply) {
            return;
        }

        $customer = Customer::query()->find($row->customer_id);

        if ($customer === null) {
            $cancel->cancel($row, reason: 'customer_missing');

            return;
        }

        if ($this->replyAlreadySentAfterRequest($customer, $row, $conversations)) {
            $cancel->cancel($row, reason: 'superseded');

            return;
        }

        $actor = User::query()->find($row->requested_by_user_id);

        if ($actor === null) {
            $row->forceFill([
                'status' => ScheduledOutboundMessageStatus::Failed,
                'failed_at' => now('UTC'),
                'failure_reason' => 'Requesting advisor no longer exists.',
            ])->save();

            return;
        }

        $payload = is_array($row->payload_json) ? $row->payload_json : [];
        $body = trim((string) ($payload['body'] ?? ''));

        if ($body === '') {
            $row->forceFill([
                'status' => ScheduledOutboundMessageStatus::Failed,
                'failed_at' => now('UTC'),
                'failure_reason' => 'Scheduled reply had no message body.',
            ])->save();

            return;
        }

        $conversation = $row->conversation_id !== null
            ? $row->conversation()->first()
            : null;
        $repairOrder = $row->repair_order_id !== null
            ? $row->repairOrder()->first()
            : null;

        try {
            $send->execute(
                customer: $customer,
                actor: $actor,
                body: $body,
                repairOrder: $repairOrder,
                conversation: $conversation,
                toPhone: $row->recipient_phone,
            );

            $row->forceFill([
                'status' => ScheduledOutboundMessageStatus::Sent,
                'sent_at' => now('UTC'),
            ])->save();
        } catch (Throwable $exception) {
            $row->forceFill([
                'status' => ScheduledOutboundMessageStatus::Failed,
                'failed_at' => now('UTC'),
                'failure_reason' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'Scheduled SMS reply failed.',
            ])->save();
        }
    }

    private function replyAlreadySentAfterRequest(
        Customer $customer,
        ScheduledOutboundMessage $row,
        ConversationResolver $conversations,
    ): bool {
        $conversationId = $row->conversation_id;

        if ($conversationId === null) {
            $conversationId = $conversations->forCustomer($customer)->id;
        }

        return ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('direction', OperationalCommunicationDirection::Outbound)
            ->where('channel', OperationalCommunicationChannel::Sms)
            ->where('created_at', '>', $row->requested_at)
            ->exists();
    }
}
