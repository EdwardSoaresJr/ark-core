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
use App\Mail\InvoicePaymentCustomerMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

final class SendPaymentLinkEmailDelivery
{
    public function __construct(
        private readonly PaymentPortalLinkContext $paymentLink,
        private readonly ConversationRecorder $conversations,
        private readonly CommunicationEventRecorder $communicationEvents,
        private readonly ArkMailClient $mail,
    ) {}

    public function send(RepairOrder $repairOrder, User $actor, string $recipientEmail): ConversationMessage
    {
        $context = $this->paymentLink->forRepairOrder($repairOrder);
        $settings = ShopSettings::current();
        $shopName = $settings->shop_name ?: config('app.name', 'ARK-SMS');
        $mailable = new InvoicePaymentCustomerMail(
            repairOrder: $repairOrder,
            shopName: $shopName,
            portalUrl: $context['url'],
            balanceDueDisplay: $context['balance_due_display'],
        );

        if (ManagedMailGate::platformSend()) {
            $this->sendViaPlatform($mailable, $repairOrder, $recipientEmail);
        } else {
            Mail::to($recipientEmail)->send($mailable);
        }

        $summary = 'Payment link emailed to '.$recipientEmail.'. Balance due '.$context['balance_due_display'].'.';

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
        InvoicePaymentCustomerMail $mailable,
        RepairOrder $repairOrder,
        string $recipientEmail,
    ): void {
        $result = $this->mail->sendTransactional([
            'operation' => 'payment_link.send',
            'to' => $recipientEmail,
            'subject' => (string) $mailable->envelope()->subject,
            'html_body' => $mailable->render(),
            'idempotency_key' => 'payment-link-send-'.Str::uuid(),
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
                    : 'Payment link email could not be sent.',
            );
        }
    }
}
