<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use RuntimeException;

final class SendReviewRequestDeliveryAction
{
    public function __construct(
        private readonly SendReviewRequestSmsAction $sms,
        private readonly SendReviewRequestEmailDelivery $email,
        private readonly ReviewRequestAuthority $authority,
        private readonly ReviewRequestProjection $projection,
    ) {}

    /**
     * @return array{
     *     messages: list<ConversationMessage>,
     *     already_sent: bool,
     *     status_label: string,
     * }
     */
    public function execute(
        RepairOrder $repairOrder,
        User $actor,
        OutboundDeliveryMode $mode,
        ?string $recipientEmail = null,
        ?Conversation $conversation = null,
    ): array {
        $projection = $this->projection->for($repairOrder);

        if ($projection['already_sent']) {
            return [
                'messages' => $this->authority->messagesFor($repairOrder)->all(),
                'already_sent' => true,
                'status_label' => (string) ($projection['status_label'] ?? 'Review Requested'),
            ];
        }

        if ($mode->includesSms() && ! $projection['can_text']) {
            throw new RuntimeException('Text is not available for this customer.');
        }

        if ($mode->includesEmail() && ! $projection['can_email'] && blank($recipientEmail)) {
            throw new RuntimeException('Email is not available for this customer.');
        }

        $messages = [];

        if ($mode->includesSms()) {
            $messages[] = $this->sms->execute($repairOrder, $actor, $conversation);
        }

        if ($mode->includesEmail()) {
            $email = strtolower(trim($recipientEmail ?? $repairOrder->customer?->email ?? ''));

            if ($email === '') {
                throw new RuntimeException('Add a customer email on file to send the review request.');
            }

            $messages[] = $this->email->send($repairOrder, $actor, $email);
        }

        $attempted = $mode->includesSms() || $mode->includesEmail();
        $messages = array_values(array_filter(
            $messages,
            fn (mixed $message): bool => $message instanceof ConversationMessage,
        ));

        if (! $attempted) {
            throw new RuntimeException('Choose text, email, or both to send a review request.');
        }

        $this->markLegacyProjection($repairOrder, $actor);

        return [
            'messages' => $messages,
            'already_sent' => false,
            'status_label' => $this->sentStatusLabel($mode, $mode->includesEmail()
                ? strtolower(trim($recipientEmail ?? $repairOrder->customer?->email ?? ''))
                : ''),
        ];
    }

    private function sentStatusLabel(OutboundDeliveryMode $mode, string $email): string
    {
        $sms = $mode->includesSms();
        $emailSent = $mode->includesEmail();

        if ($sms && $emailSent) {
            return $email !== ''
                ? 'Review request sent by text and email to '.$email.'.'
                : 'Review request sent by text and email.';
        }

        if ($emailSent) {
            return $email !== ''
                ? 'Review request sent by email to '.$email.'.'
                : 'Review request sent by email.';
        }

        return 'Review request sent by text.';
    }

    private function markLegacyProjection(RepairOrder $repairOrder, User $actor): void
    {
        $repairOrder->forceFill([
            'review_request_sent' => true,
            'review_not_requested_reason' => null,
            'review_request_recorded_at' => now(),
            'review_request_recorded_by' => $actor->id,
        ])->save();
    }
}
