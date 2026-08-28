<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Mail\ReviewRequestCustomerMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

final class SendReviewRequestEmailDelivery
{
    public function __construct(
        private readonly ConversationRecorder $conversations,
        private readonly CommunicationEventRecorder $communicationEvents,
    ) {}

    public function send(RepairOrder $repairOrder, User $actor, string $recipientEmail): ConversationMessage
    {
        $shopName = ReviewRequestCopy::shopName();
        $reviewUrl = ReviewRequestCopy::reviewUrl();
        $contactUrl = ReviewRequestCopy::contactUrl();

        Mail::to($recipientEmail)->send(new ReviewRequestCustomerMail(
            repairOrder: $repairOrder,
            shopName: $shopName,
            reviewUrl: $reviewUrl,
            contactUrl: $contactUrl,
            shopPhone: ReviewRequestCopy::shopPhoneDisplay(),
        ));

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
}
