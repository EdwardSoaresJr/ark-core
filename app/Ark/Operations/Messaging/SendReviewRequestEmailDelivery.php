<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Platform\Mail\ArkMailClient;
use App\Ark\Platform\Mail\ManagedMailGate;
use App\Mail\ReviewRequestCustomerMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

final class SendReviewRequestEmailDelivery
{
    public function __construct(
        private readonly ConversationRecorder $conversations,
        private readonly CommunicationEventRecorder $communicationEvents,
        private readonly ArkMailClient $mail,
    ) {}

    public function send(RepairOrder $repairOrder, User $actor, string $recipientEmail): ConversationMessage
    {
        $shopName = ReviewRequestCopy::shopName();
        $reviewUrl = ReviewRequestCopy::reviewUrl();
        $contactUrl = ReviewRequestCopy::contactUrl();
        $mailable = new ReviewRequestCustomerMail(
            repairOrder: $repairOrder,
            shopName: $shopName,
            reviewUrl: $reviewUrl,
            contactUrl: $contactUrl,
            shopPhone: ReviewRequestCopy::shopPhoneDisplay(),
        );

        if (ManagedMailGate::platformSend()) {
            $this->sendViaPlatform($mailable, $repairOrder, $recipientEmail);
        } else {
            Mail::to($recipientEmail)->send($mailable);
        }

        $summary = 'Review request emailed to '.$recipientEmail.'.';

        $message = $this->conversations->recordRepairOrderEmail(
            $repairOrder,
            $actor,
            $recipientEmail,
            $summary,
            metadata: [
                'kind' => ReviewRequestAuthority::METADATA_KIND,
                'review_url' => $reviewUrl,
                'contact_url' => $contactUrl,
            ],
        );

        $this->communicationEvents->record(
            $repairOrder,
            OperationalCommunicationType::ApprovalFollowUp,
            OperationalCommunicationChannel::Email,
            OperationalCommunicationDirection::Outbound,
            $summary,
            actor: $actor,
            message: $message,
        );

        return $message;
    }

    private function sendViaPlatform(
        ReviewRequestCustomerMail $mailable,
        RepairOrder $repairOrder,
        string $recipientEmail,
    ): void {
        $result = $this->mail->sendTransactional([
            'operation' => 'review_request.send',
            'to' => $recipientEmail,
            'subject' => (string) $mailable->envelope()->subject,
            'html_body' => $mailable->render(),
            'idempotency_key' => 'review-request-send-'.Str::uuid(),
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
                    : 'Review request email could not be sent.',
            );
        }
    }
}
