<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Documents\EstimateDocumentEmailDelivery;
use App\Ark\Operations\Documents\EstimatePdfUnavailableException;
use App\Ark\Operations\RepairOrders\LearnEstimateCompanionPatternsAction;
use App\Ark\Operations\RepairOrders\MarkEstimateAwaitingCustomerApprovalAction;
use App\Ark\Operations\RepairOrders\RecordEstimateSentWithMissingVinAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use RuntimeException;

final class SendEstimateDeliveryAction
{
    public function __construct(
        private readonly SendEstimateLinkAction $sms,
        private readonly EstimateDocumentEmailDelivery $email,
        private readonly RecordEstimateSentWithMissingVinAction $recordMissingVinOverride,
        private readonly LearnEstimateCompanionPatternsAction $learnCompanions,
    ) {}

    /**
     * @return array{
     *     messages: list<ConversationMessage>,
     *     estimate_url: ?string,
     *     token_reused: ?bool,
     *     awaiting_approval: array{
     *         moved: bool,
     *         from_status: string,
     *         to_status: string|null,
     *         reason: string,
     *         blocking_message: string|null,
     *         toast: string|null,
     *     }|null,
     * }
     */
    public function execute(
        RepairOrder $repairOrder,
        User $actor,
        OutboundDeliveryMode $mode,
        ?string $recipientEmail = null,
        ?string $staffNote = null,
        bool $acknowledgeMissingVin = false,
        ?string $recipientPhone = null,
        ?Conversation $conversation = null,
        bool $acknowledgeTimingFluids = false,
    ): array {
        $repairOrder->ensureEstimateSendAllowed($acknowledgeMissingVin, $acknowledgeTimingFluids);

        $messages = [];
        $estimateUrl = null;
        $tokenReused = null;
        $awaitingApproval = null;

        if ($mode->includesSms()) {
            $smsResult = $this->sms->execute(
                $repairOrder,
                $actor,
                $conversation,
                recipientPhone: $recipientPhone,
            );
            $messages[] = $smsResult['message'];
            $estimateUrl = $smsResult['url'];
            $tokenReused = $smsResult['token_reused'];
            $awaitingApproval = MarkEstimateAwaitingCustomerApprovalAction::prefer(
                $awaitingApproval,
                $smsResult['awaiting_approval'],
            );
        }

        if ($mode->includesEmail()) {
            if ($repairOrder->lines()->doesntExist()) {
                throw new RuntimeException('Add at least one estimate line before emailing the customer.');
            }

            $email = strtolower(trim($recipientEmail ?? $repairOrder->customer->email ?? ''));

            if ($email === '') {
                throw new RuntimeException('Add a customer email on file or enter one to send the estimate.');
            }

            try {
                $emailResult = $this->email->send($repairOrder, $actor, $email, $staffNote);
            } catch (EstimatePdfUnavailableException) {
                throw new RuntimeException('Estimate email failed. The PDF could not be generated — check Chromium runtime support.');
            }

            $messages[] = $emailResult['message'];
            $awaitingApproval = MarkEstimateAwaitingCustomerApprovalAction::prefer(
                $awaitingApproval,
                $emailResult['awaiting_approval'],
            );
        }

        if ($messages === []) {
            throw new RuntimeException('Choose SMS, email, or both to send the estimate.');
        }

        if ($acknowledgeTimingFluids) {
            $this->learnCompanions->recordExceptions($repairOrder);
        }

        $this->learnCompanions->ingest($repairOrder);

        if ($acknowledgeMissingVin) {
            if ($mode->includesSms()) {
                $this->recordMissingVinOverride->record($repairOrder, $actor, 'portal');
            }

            if ($mode->includesEmail()) {
                $this->recordMissingVinOverride->record($repairOrder, $actor, 'email');
            }
        }

        return [
            'messages' => $messages,
            'estimate_url' => $estimateUrl,
            'token_reused' => $tokenReused,
            'awaiting_approval' => $awaitingApproval,
        ];
    }
}
