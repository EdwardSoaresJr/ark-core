<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\EstimateTotalsCalculator;
use App\Ark\Operations\Portal\CreateOrReuseEstimateAccessTokenAction;
use App\Ark\Operations\RepairOrders\MarkEstimateAwaitingCustomerApprovalAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Mail\OutboundTransactionalMail;
use App\Ark\Mail\TransactionalMailException;
use App\Ark\Mail\TransactionalMailOperation;
use App\Mail\EstimateCustomerMail;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

class EstimateDocumentEmailDelivery
{
    public function __construct(
        private readonly EstimateDocumentService $documents,
        private readonly EstimateTotalsCalculator $calculator,
        private readonly OperationalEventRecorder $events,
        private readonly ConversationRecorder $conversations,
        private readonly CommunicationEventRecorder $communicationEvents,
        private readonly CreateOrReuseEstimateAccessTokenAction $estimateTokens,
        private readonly MarkEstimateAwaitingCustomerApprovalAction $markAwaitingApproval,
        private readonly OutboundTransactionalMail $outboundMail,
    ) {}

    /**
     * @return array{
     *     message: ConversationMessage,
     *     awaiting_approval: array{
     *         moved: bool,
     *         from_status: string,
     *         to_status: string|null,
     *         reason: string,
     *         blocking_message: string|null,
     *         toast: string|null,
     *     },
     * }
     */
    public function send(RepairOrder $repairOrder, User $actor, string $recipientEmail, ?string $staffNote = null): array
    {
        $repairOrder->loadMissing(['customer', 'vehicle']);

        try {
            $document = $this->documents->resolveForRepairOrder($repairOrder, $actor);
        } catch (Throwable) {
            throw EstimatePdfUnavailableException::forRepairOrder($repairOrder->repair_order_id);
        }

        if (! $this->documents->hasViewablePdf($document)) {
            throw EstimatePdfUnavailableException::forRepairOrder($repairOrder->repair_order_id);
        }

        $settings = ShopSettings::current();
        $shopName = $settings->shop_name ?: config('app.name', 'ARK-SMS');
        $totals = $this->calculator->totalsFor($repairOrder);
        $pdfFilename = sprintf('estimate-ro-%d.pdf', $repairOrder->repair_order_id);
        $accessToken = $this->estimateTokens->execute($repairOrder, $actor);
        $portalUrl = route('portal.estimates.show', ['token' => $accessToken->plainToken]);

        $mailable = new EstimateCustomerMail(
            repairOrder: $repairOrder,
            totals: $totals,
            shopName: $shopName,
            portalUrl: $portalUrl,
            pdfPath: $document->pdf_path,
            pdfFilename: $pdfFilename,
            staffNote: filled($staffNote) ? trim($staffNote) : null,
        );

        $attachments = [];
        if (filled($document->pdf_path) && is_file($document->pdf_path)) {
            $attachments[] = [
                'filename' => $pdfFilename,
                'mime' => 'application/pdf',
                'path' => $document->pdf_path,
            ];
        }

        $mailResult = $this->outboundMail->sendMailable(
            TransactionalMailOperation::EstimateSend,
            $recipientEmail,
            $mailable,
            'estimate-'.$repairOrder->repair_order_id.'-'.Str::uuid(),
            'repair_order',
            (string) $repairOrder->repair_order_id,
            $attachments,
        );

        if (! $mailResult->ok()) {
            throw new TransactionalMailException($mailResult);
        }

        $summary = 'Estimate emailed to '.$recipientEmail.' with portal review link.';

        if (filled($staffNote)) {
            $summary .= ' Note: '.trim($staffNote);
        }

        $message = $this->conversations->recordRepairOrderEmail(
            $repairOrder,
            $actor,
            $recipientEmail,
            $summary,
        );

        $event = $this->communicationEvents->record(
            $repairOrder,
            OperationalCommunicationType::EstimateSent,
            OperationalCommunicationChannel::Email,
            OperationalCommunicationDirection::Outbound,
            $summary,
            actor: $actor,
            message: $message,
        );

        $this->events->record(
            OperationalEventName::EstimateEmailedToCustomer,
            $repairOrder,
            actor: $actor,
            payload: [
                'repair_order_id' => $repairOrder->id,
                'estimate_document_id' => $document->id,
                'communication_event_id' => $event->id,
                'conversation_message_id' => $message->id,
                'recipient_email' => $recipientEmail,
            ],
        );

        $awaitingApproval = $this->markAwaitingApproval->execute($repairOrder->fresh(), $actor);

        return [
            'message' => $message,
            'awaiting_approval' => $awaitingApproval,
        ];
    }
}
