<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Mail\EmailIntent;
use App\Ark\Platform\Mail\ArkMailClient;
use App\Ark\Platform\Mail\ManagedMailGate;
use App\Models\User;
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
        if (! ManagedMailGate::platformSend()) {
            throw new RuntimeException("Email isn't configured yet.");
        }

        $variables = [
            'shop_name' => $shopName,
            'review_url' => $reviewUrl,
            'contact_url' => $contactUrl,
        ];
        $phone = ReviewRequestCopy::shopPhoneDisplay();
        if (filled($phone)) {
            $variables['shop_phone'] = $phone;
        }

        $this->sendViaPlatform($repairOrder, $recipientEmail, $variables);

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

    /**
     * @param  array<string, string>  $variables
     */
    private function sendViaPlatform(
        RepairOrder $repairOrder,
        string $recipientEmail,
        array $variables,
    ): void {
        $result = $this->mail->sendTransactional(EmailIntent::payload(
            operation: 'review_request.send',
            to: $recipientEmail,
            variables: $variables,
            idempotencyKey: 'review-request-send-'.Str::uuid(),
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
                    : 'Review request email could not be sent.',
            );
        }
    }
}
