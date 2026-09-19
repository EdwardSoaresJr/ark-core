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
use App\Ark\Platform\Mail\ArkMailClient;
use App\Ark\Platform\Mail\ManagedMailGate;
use App\Mail\DepositRequestCustomerMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
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
        $mailable = new DepositRequestCustomerMail(
            repairOrder: $repairOrder,
            shopName: $shopName,
            portalUrl: $context['url'],
            amountDisplay: $context['amount_display'],
        );

        if (ManagedMailGate::platformSend()) {
            $this->sendViaPlatform($mailable, $repairOrder, $recipientEmail);
        } else {
            Mail::to($recipientEmail)->send($mailable);
        }

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

    private function sendViaPlatform(
        DepositRequestCustomerMail $mailable,
        RepairOrder $repairOrder,
        string $recipientEmail,
    ): void {
        $result = $this->mail->sendTransactional([
            'operation' => 'deposit_request.send',
            'to' => $recipientEmail,
            'subject' => (string) $mailable->envelope()->subject,
            'html_body' => $mailable->render(),
            'idempotency_key' => 'deposit-request-send-'.Str::uuid(),
            'domain_object_type' => 'repair_order',
            'domain_object_id' => (string) $repairOrder->id,
            'metadata' => [
                'repair_order_id' => $repairOrder->id,
            ],
        ]);

        if (($result['ok'] ?? false) !== true) {
            throw new RuntimeException(
                is_string($result['message'] ?? null)
                    ? $result['message']
                    : 'Deposit request email could not be sent.',
            );
        }
    }
}
