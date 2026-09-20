<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Financial\BalanceDueCalculator;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Payments\CreateCustomerPayTokenAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Mail\ArkMailClient;
use App\Ark\Platform\Mail\ManagedMailGate;
use App\Mail\InvoiceCustomerMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class InvoiceDocumentEmailDelivery
{
    public function __construct(
        private readonly EstimateDocumentService $documents,
        private readonly BalanceDueCalculator $balanceDue,
        private readonly CreateCustomerPayTokenAction $payTokens,
        private readonly OperationalEventRecorder $events,
        private readonly ConversationRecorder $conversations,
        private readonly CommunicationEventRecorder $communicationEvents,
        private readonly ArkMailClient $mail,
    ) {}

    public function send(RepairOrder $repairOrder, User $actor, string $recipientEmail, ?string $staffNote = null): void
    {
        $repairOrder->loadMissing(['customer', 'vehicle']);

        $balance = $this->balanceDue->forRepairOrder($repairOrder);

        abort_unless($balance->hasIssuedInvoice, 422, 'Generate the final invoice before emailing it to the customer.');

        $invoice = $repairOrder->estimateDocuments()
            ->where('document_type', FinancialDocumentType::Invoice->value)
            ->latest('id')
            ->first();

        if ($invoice === null) {
            abort(422, 'Final invoice could not be found for this repair order.');
        }

        try {
            if (! $this->documents->hasViewablePdf($invoice)) {
                $this->documents->generatePdf($invoice);
            }
        } catch (Throwable) {
            throw EstimatePdfUnavailableException::forRepairOrder($repairOrder->repair_order_id);
        }

        if (! $this->documents->hasViewablePdf($invoice)) {
            throw EstimatePdfUnavailableException::forRepairOrder($repairOrder->repair_order_id);
        }

        $payUrl = null;

        if (\App\Ark\Platform\Payments\ManagedPaymentsGate::platformCapture() && $balance->balanceDueCents > 0) {
            $token = $this->payTokens->execute($repairOrder, $invoice);
            $payUrl = route('portal.invoice-pay.show', ['token' => $token->plainToken]);
        }

        $settings = ShopSettings::current();
        $shopName = $settings->shop_name ?: config('app.name', 'ARK-SMS');
        $pdfFilename = sprintf('invoice-ro-%d.pdf', $repairOrder->repair_order_id);
        $mailable = new InvoiceCustomerMail(
            repairOrder: $repairOrder,
            balanceDueCents: $balance->balanceDueCents,
            shopName: $shopName,
            pdfPath: $invoice->pdf_path,
            pdfFilename: $pdfFilename,
            staffNote: filled($staffNote) ? trim($staffNote) : null,
            payUrl: $payUrl,
        );

        if (ManagedMailGate::platformSend()) {
            $this->sendViaPlatform($mailable, $repairOrder, $invoice, $recipientEmail, $pdfFilename);
        } else {
            Mail::to($recipientEmail)->send($mailable);
        }

        $summary = 'Final invoice emailed to '.$recipientEmail.'.';

        if ($payUrl !== null) {
            $summary .= ' Pay link included.';
        }

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
            OperationalCommunicationType::InvoiceSent,
            OperationalCommunicationChannel::Email,
            OperationalCommunicationDirection::Outbound,
            $summary,
            actor: $actor,
            message: $message,
        );

        $this->events->record(
            OperationalEventName::InvoiceEmailedToCustomer,
            $repairOrder,
            actor: $actor,
            payload: [
                'repair_order_id' => $repairOrder->id,
                'invoice_document_id' => $invoice->id,
                'communication_event_id' => $event->id,
                'conversation_message_id' => $message->id,
                'recipient_email' => $recipientEmail,
                'pay_link_included' => $payUrl !== null,
            ],
        );

        $invoice->markPresentedToCustomer();
    }

    private function sendViaPlatform(
        InvoiceCustomerMail $mailable,
        RepairOrder $repairOrder,
        EstimateDocument $invoice,
        string $recipientEmail,
        string $pdfFilename,
    ): void {
        $pdf = Storage::disk('local')->get($invoice->pdf_path);

        if (! is_string($pdf) || $pdf === '') {
            throw EstimatePdfUnavailableException::forRepairOrder($repairOrder->repair_order_id);
        }

        $result = $this->mail->sendTransactional([
            'operation' => 'invoice.send',
            'to' => $recipientEmail,
            'subject' => (string) $mailable->envelope()->subject,
            'html_body' => $mailable->render(),
            'attachments' => [[
                'filename' => $pdfFilename,
                'mime' => 'application/pdf',
                'content_base64' => base64_encode($pdf),
            ]],
            'idempotency_key' => 'invoice-send-'.Str::uuid(),
            'domain_object_type' => 'repair_order',
            'domain_object_id' => (string) $repairOrder->id,
            'metadata' => [
                'repair_order_id' => $repairOrder->id,
                'invoice_document_id' => $invoice->id,
            ],
        ]);

        if (($result['ok'] ?? false) !== true) {
            throw new RuntimeException(
                is_string($result['message'] ?? null)
                    ? $result['message']
                    : 'Invoice email could not be sent.',
            );
        }
    }
}
