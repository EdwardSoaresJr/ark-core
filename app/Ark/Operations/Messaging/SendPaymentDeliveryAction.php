<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use RuntimeException;

final class SendPaymentDeliveryAction
{
    public function __construct(
        private readonly SendPaymentLinkAction $sms,
        private readonly SendPaymentLinkEmailDelivery $email,
    ) {}

    /**
     * @return array{
     *     messages: list<ConversationMessage>,
     *     payment_url: ?string,
     *     balance_due_display: ?string,
     * }
     */
    public function execute(
        RepairOrder $repairOrder,
        User $actor,
        OutboundDeliveryMode $mode,
        ?string $recipientEmail = null,
        ?Conversation $conversation = null,
    ): array {
        $messages = [];
        $paymentUrl = null;
        $balanceDueDisplay = null;

        if ($mode->includesSms()) {
            $smsResult = $this->sms->execute($repairOrder, $actor, $conversation);
            $messages[] = $smsResult['message'];
            $paymentUrl = $smsResult['url'];
            $balanceDueDisplay = $smsResult['balance_due_display'];
        }

        if ($mode->includesEmail()) {
            $email = strtolower(trim($recipientEmail ?? $repairOrder->customer->email ?? ''));

            if ($email === '') {
                throw new RuntimeException('Add a customer email on file or enter one to send the payment link.');
            }

            $messages[] = $this->email->send($repairOrder, $actor, $email);
        }

        if ($messages === []) {
            throw new RuntimeException('Choose SMS, email, or both to send the payment link.');
        }

        return [
            'messages' => $messages,
            'payment_url' => $paymentUrl,
            'balance_due_display' => $balanceDueDisplay,
        ];
    }
}
