<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Brick\Money\Money;
use RuntimeException;

final class SendDepositRequestDeliveryAction
{
    public function __construct(
        private readonly SendDepositRequestLinkAction $sms,
        private readonly SendDepositRequestEmailDelivery $email,
        private readonly EstimateTotalsCalculator $totalsCalculator,
    ) {}

    /**
     * @return array{
     *     messages: list<ConversationMessage>,
     *     deposit_url: ?string,
     *     amount_display: ?string,
     * }
     */
    public function execute(
        RepairOrder $repairOrder,
        User $actor,
        OutboundDeliveryMode $mode,
        float|string|int $amount,
        ?string $recipientEmail = null,
        ?Conversation $conversation = null,
    ): array {
        $amountCents = $this->totalsCalculator->unitPriceCents($amount);

        $messages = [];
        $depositUrl = null;
        $amountDisplay = null;

        if ($mode->includesSms()) {
            $smsResult = $this->sms->execute($repairOrder, $actor, $amountCents, $conversation);
            $messages[] = $smsResult['message'];
            $depositUrl = $smsResult['url'];
            $amountDisplay = $smsResult['amount_display'];
        }

        if ($mode->includesEmail()) {
            $email = strtolower(trim($recipientEmail ?? $repairOrder->customer->email ?? ''));

            if ($email === '') {
                throw new RuntimeException('Add a customer email on file or enter one to send the deposit request.');
            }

            $messages[] = $this->email->send($repairOrder, $actor, $email, $amountCents);
            $amountDisplay ??= $this->formatAmount($amountCents);
        }

        if ($messages === []) {
            throw new RuntimeException('Choose SMS, email, or both to send the deposit request.');
        }

        return [
            'messages' => $messages,
            'deposit_url' => $depositUrl,
            'amount_display' => $amountDisplay,
        ];
    }

    private function formatAmount(int $amountCents): string
    {
        return Money::ofMinor($amountCents, 'USD')->formatTo('en_US');
    }
}
