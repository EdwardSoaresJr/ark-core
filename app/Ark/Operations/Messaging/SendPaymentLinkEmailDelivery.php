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
use App\Mail\InvoicePaymentCustomerMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

final class SendPaymentLinkEmailDelivery
{
    public function __construct(
        private readonly PaymentPortalLinkContext $paymentLink,
        private readonly ConversationRecorder $conversations,
        private readonly CommunicationEventRecorder $communicationEvents,
    ) {}

    public function send(RepairOrder $repairOrder, User $actor, string $recipientEmail): ConversationMessage
    {
        $context = $this->paymentLink->forRepairOrder($repairOrder);
        $settings = ShopSettings::current();
        $shopName = $settings->shop_name ?: config('app.name', 'ARK-SMS');

        Mail::to($recipientEmail)->send(new InvoicePaymentCustomerMail(
            repairOrder: $repairOrder,
            shopName: $shopName,
            portalUrl: $context['url'],
            balanceDueDisplay: $context['balance_due_display'],
        ));

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
}
