<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Mail\EmailIntent;
use App\Ark\Platform\Mail\ArkMailClient;
use App\Ark\Platform\Mail\ManagedMailGate;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

final class SendDepositRequestEmailDelivery
{
    public function __construct(
        private readonly DepositPortalLinkContext $depositLink,
        private readonly ConversationRecorder $conversations,
        private readonly CommunicationEventRecorder $communicationEvents,
        private readonly ArkMailClient $mail,
    ) {}

    public function send(
        RepairOrder $repairOrder,
        User $actor,
        string $recipientEmail,
        int $amountCents,
    ): ConversationMessage {
        $context = $this->depositLink->forRepairOrder($repairOrder, $amountCents);
        $settings = ShopSettings::current();
        $shopName = $settings->shop_name ?: config('app.name', 'ARK-SMS');
        if (! ManagedMailGate::platformSend()) {
            throw new RuntimeException("Email isn't configured yet.");
        }

        $repairOrder->loadMissing('vehicle');

        $this->sendViaPlatform($repairOrder, $recipientEmail, [
            'shop_name' => $shopName,
            'vehicle' => (string) ($repairOrder->vehicle?->display_name ?? 'vehicle'),
            'repair_order_number' => (string) $repairOrder->repair_order_id,
            'amount' => (string) $context['amount_display'],
            'pay_url' => (string) $context['url'],
        ]);

        $summary = 'Deposit request emailed to '.$recipientEmail.'. Amount '.$context['amount_display'].'.';

        $message = $this->conversations->recordRepairOrderEmail(
            $repairOrder,
            $actor,
            $recipientEmail,
            $summary,
        );

        $this->communicationEvents->record(
            $repairOrder,
            OperationalCommunicationType::InvoiceSent,
            OperationalCommunicationChannel::Email,
            OperationalCommunicationDirection::Outbound,
            $summary,
            actor: $actor,
            message: $message,
        );

        return $message;
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function sendViaPlatform(
        RepairOrder $repairOrder,
        string $recipientEmail,
        array $variables,
    ): void {
        $result = $this->mail->sendTransactional(EmailIntent::payload(
            operation: 'deposit_request.send',
            to: $recipientEmail,
            variables: $variables,
            idempotencyKey: 'deposit-request-send-'.Str::uuid(),
            domainObjectType: 'repair_order',
            domainObjectId: (string) $repairOrder->id,
            metadata: [
                'repair_order_id' => $repairOrder->id,
            ],
        ));

        if (($result['ok'] ?? false) !== true) {
            throw new RuntimeException(
                is_string($result['message'] ?? null)
                    ? $result['message']
                    : 'Deposit request email could not be sent.',
            );
        }
    }
}
