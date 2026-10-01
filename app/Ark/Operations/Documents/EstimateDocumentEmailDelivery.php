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
use App\Ark\Mail\EmailIntent;
use App\Ark\Platform\Mail\ArkMailClient;
use App\Ark\Platform\Mail\ManagedMailGate;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use Illuminate\Support\Str;

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
        private readonly ArkMailClient $mail,
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

        $document = $this->documents->attachablePdfForRepairOrder($repairOrder);

        if ($document === null) {
            try {
                $document = $this->documents->resolveForRepairOrder($repairOrder, $actor);
            } catch (Throwable) {
                throw EstimatePdfUnavailableException::forRepairOrder($repairOrder->repair_order_id);
            }

            if (! $this->documents->hasViewablePdf($document)) {
                throw EstimatePdfUnavailableException::forRepairOrder($repairOrder->repair_order_id);
            }
        }

        $settings = ShopSettings::current();
        $shopName = $settings->shop_name ?: config('app.name', 'ARK-SMS');
        $totals = $this->calculator->totalsFor($repairOrder);
        $pdfFilename = sprintf('estimate-ro-%d.pdf', $repairOrder->repair_order_id);
        $accessToken = $this->estimateTokens->execute($repairOrder, $actor);
        $portalUrl = route('portal.estimates.show', ['token' => $accessToken->plainToken]);

        if (! ManagedMailGate::platformSend()) {
            throw new RuntimeException("Email isn't configured yet.");
        }

        $this->sendViaPlatform($repairOrder, $document, $recipientEmail, $pdfFilename, [
            'shop_name' => $shopName,
            'vehicle' => (string) ($repairOrder->vehicle?->display_name ?? 'vehicle'),
            'repair_order_number' => (string) $repairOrder->repair_order_id,
            'estimate_total' => $totals->format($totals->totalCents()),
            'portal_url' => $portalUrl,
            'staff_note' => filled($staffNote) ? trim($staffNote) : '',
        ]);

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

    /**
     * @param  array<string, string>  $variables
     */
    private function sendViaPlatform(
        RepairOrder $repairOrder,
        EstimateDocument $document,
        string $recipientEmail,
        string $pdfFilename,
        array $variables,
    ): void {
        $pdf = Storage::disk('local')->get($document->pdf_path);

        if (! is_string($pdf) || $pdf === '') {
            throw EstimatePdfUnavailableException::forRepairOrder($repairOrder->repair_order_id);
        }

        $result = $this->mail->sendTransactional(EmailIntent::payload(
            operation: 'estimate.send',
            to: $recipientEmail,
            variables: $variables,
            idempotencyKey: 'estimate-send-'.Str::uuid(),
            domainObjectType: 'repair_order',
            domainObjectId: (string) $repairOrder->id,
            attachments: [[
                'filename' => $pdfFilename,
                'mime' => 'application/pdf',
                'content_base64' => base64_encode($pdf),
            ]],
            metadata: [
                'repair_order_id' => $repairOrder->id,
                'estimate_document_id' => $document->id,
            ],
        ));

        if (($result['ok'] ?? false) !== true) {
            throw new RuntimeException(
                is_string($result['message'] ?? null)
                    ? $result['message']
                    : 'Estimate email could not be sent.',
            );
        }
    }
}
