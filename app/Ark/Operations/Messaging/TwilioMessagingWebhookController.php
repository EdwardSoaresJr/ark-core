<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Platform\Communications\ManagedCommunicationsGate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TwilioMessagingWebhookController
{
    public function __construct(
        private readonly MessagingAccountResolver $accounts,
        private readonly ReceiveTwilioInboundMessage $inboundMessages,
        private readonly ApplyTwilioDeliveryStatus $deliveryStatus,
    ) {}

    public function inbound(Request $request): Response
    {
        return $this->accept($request, inbound: true);
    }

    public function status(Request $request): Response
    {
        return $this->accept($request, inbound: false);
    }

    private function accept(Request $request, bool $inbound): Response
    {
        if (ManagedCommunicationsGate::rejectCoreTwilioMessagingWebhooks()) {
            return response('Messaging webhook is not accepted.', 404);
        }

        $account = $this->accounts->current();
        if ($account === null || ! $account->complete()) {
            return response('Messaging is not configured.', 503);
        }

        $params = $request->request->all();
        $signature = (string) $request->header('X-Twilio-Signature', '');
        if (! TwilioRequestSignature::valid($request->fullUrl(), $params, $account->authToken, $signature)) {
            return response('Invalid signature.', 401);
        }

        $shopNumber = PhoneNumber::toE164($account->from);
        $presented = PhoneNumber::toE164((string) ($params[$inbound ? 'To' : 'From'] ?? ''));
        if ($shopNumber === null || $presented !== $shopNumber) {
            return response('Unknown messaging number.', 403);
        }

        $receiptKey = $this->receiptKey($params, $inbound);
        if ($receiptKey === '') {
            return response('Incomplete messaging callback.', 422);
        }
        if (MessagingWebhookReceipt::query()->where('receipt_key', $receiptKey)->exists()) {
            return response('', 204);
        }

        $confirmation = null;
        try {
            if ($inbound) {
                $confirmation = $this->inboundMessages->receive($params)['confirmation'] ?? null;
            } elseif (! $this->deliveryStatus->apply($params)) {
                return response('', 404);
            }
        } catch (\InvalidArgumentException) {
            return response('Messaging media was rejected.', 400);
        }

        MessagingWebhookReceipt::query()->create(['receipt_key' => $receiptKey]);

        if (is_string($confirmation) && $confirmation !== '') {
            $message = htmlspecialchars($confirmation, ENT_XML1);

            return response(
                '<?xml version="1.0" encoding="UTF-8"?><Response><Message>'.$message.'</Message></Response>',
                200,
                ['Content-Type' => 'text/xml; charset=UTF-8'],
            );
        }

        return response('', 204);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function receiptKey(array $params, bool $inbound): string
    {
        $sid = trim((string) ($params['MessageSid'] ?? ''));
        if ($sid === '') {
            return '';
        }
        if ($inbound) {
            return hash('sha256', 'inbound|'.$sid);
        }

        $status = strtolower(trim((string) ($params['MessageStatus'] ?? '')));
        $error = trim((string) ($params['ErrorCode'] ?? ''));
        if ($status === '') {
            return '';
        }

        return hash('sha256', 'status|'.$sid.'|'.$status.'|'.$error);
    }
}
