<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Mail\ArkMailClient;
use App\Ark\Mail\TransactionalMailEnvelope;
use App\Ark\Mail\TransactionalMailOperation;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Messaging\ResolvePhoneSmsCapabilityAction;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Platform\Communications\CommunicationMessageContext;
use App\Ark\Platform\Mail\ManagedMailGate;
use App\Ark\Texting\PlatformOutboundSmsTransport;
use App\Support\Mail\ShopMailBranding;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWebsiteLeadConfirmationAction
{
    public function __construct(
        private readonly OutboundSmsTransport $transport,
        private readonly ConversationRecorder $recorder,
        private readonly ShopIntegrationCredentials $credentials,
        private readonly LeadConfirmationAuditConversation $confirmationAudit,
        private readonly ResolvePhoneSmsCapabilityAction $smsCapability,
        private readonly ArkMailClient $mail,
    ) {}

    public function execute(Lead $lead): void
    {
        if (! config('public_lead.send_confirmation', true)) {
            return;
        }

        if ($lead->state === LeadState::Spam) {
            return;
        }

        $lead->loadMissing('conversation');

        if ($lead->conversation === null) {
            return;
        }

        $this->sendSms($lead);
        $this->sendEmail($lead);
    }

    private function sendSms(Lead $lead): void
    {
        if (! filled($lead->contact_phone) || ! $this->credentials->twilioConfigured()) {
            return;
        }

        $conversation = $lead->conversation;

        if ($conversation->contact_surface !== ConversationContactSurface::Phone) {
            return;
        }

        $customer = Customer::query()
            ->where('phone', $lead->contact_phone)
            ->first();

        if ($customer instanceof Customer) {
            $eligibility = CustomerSmsSendEligibility::for($customer, $this->credentials);

            if (! $eligibility->canSend()) {
                return;
            }
        }

        $capability = $this->smsCapability->execute((string) $lead->contact_phone);

        if ($capability !== null && ! $capability->sms_capable) {
            return;
        }

        try {
            $body = WebsiteLeadConfirmationCopy::smsBody($lead);
            $result = $this->transport->send(
                $lead->contact_phone,
                $body,
                [],
                CommunicationMessageContext::reference([], 'website_lead', $lead->id),
            );

            if ($this->transport instanceof PlatformOutboundSmsTransport) {
                return;
            }

            $this->recorder->recordSystemOutboundSms(
                $conversation,
                $body,
                $result->messageId,
                metadata: [
                    'website_lead_confirmation' => true,
                    'lead_id' => $lead->id,
                ],
            );
        } catch (Throwable $exception) {
            Log::warning('website_lead_confirmation_sms_failed', [
                'lead_id' => $lead->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function sendEmail(Lead $lead): void
    {
        $email = trim((string) ($lead->contact_email ?? ''));

        if ($email === '') {
            return;
        }

        try {
            $viewData = WebsiteLeadConfirmationCopy::emailViewData($lead);

            if (! ManagedMailGate::platformSend()) {
                Log::warning('website_lead_confirmation_email_failed', [
                    'lead_id' => $lead->id,
                    'reason_code' => 'email_not_configured',
                ]);

                return;
            }

            $variables = [
                'shop_name' => ShopMailBranding::shopName(),
                'intro' => (string) $viewData['intro'],
            ];
            if (filled($viewData['response_hint'] ?? null)) {
                $variables['response_hint'] = (string) $viewData['response_hint'];
            }
            if (filled($viewData['phone_display'] ?? null)) {
                $variables['phone_display'] = (string) $viewData['phone_display'];
            }

            $result = $this->mail->send(TransactionalMailEnvelope::intent(
                operation: TransactionalMailOperation::CustomerTransactionalMessage,
                recipientEmail: $email,
                variables: $variables,
                idempotencyKey: 'website-lead-confirmation-'.$lead->uuid,
                domainObjectType: 'lead',
                domainObjectId: (string) $lead->uuid,
            ));

            if (! $result->ok()) {
                Log::warning('website_lead_confirmation_email_failed', [
                    'lead_id' => $lead->id,
                    'reason_code' => $result->reasonCode,
                    'message' => $result->message,
                ]);

                return;
            }

            $emailConversation = $this->emailConversation($email);

            $this->recorder->recordSystemEmail(
                $emailConversation,
                $email,
                'Website request confirmation emailed to '.$email.'.',
                metadata: [
                    'website_lead_confirmation' => true,
                    'lead_id' => $lead->id,
                ],
            );

            $this->confirmationAudit->finalizeEmailConfirmationAudit($lead, $emailConversation);
        } catch (Throwable $exception) {
            Log::warning('website_lead_confirmation_email_failed', [
                'lead_id' => $lead->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function emailConversation(string $email): Conversation
    {
        return app(ConversationResolver::class)->forEmail($email);
    }
}
