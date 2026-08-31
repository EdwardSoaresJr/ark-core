<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
use App\Ark\Operations\Messaging\ResolvePhoneSmsCapabilityAction;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Mail\WebsiteLeadConfirmationMail;
use App\Support\Mail\ShopMailBranding;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendWebsiteLeadConfirmationAction
{
    public function __construct(
        private readonly OutboundSmsTransport $transport,
        private readonly ConversationRecorder $recorder,
        private readonly ShopIntegrationCredentials $credentials,
        private readonly LeadConfirmationAuditConversation $confirmationAudit,
        private readonly ResolvePhoneSmsCapabilityAction $smsCapability,
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
            $result = $this->transport->send($lead->contact_phone, $body);

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

            Mail::to($email)->send(new WebsiteLeadConfirmationMail(
                shopName: ShopMailBranding::shopName(),
                subjectLine: WebsiteLeadConfirmationCopy::emailSubject($lead),
                intro: $viewData['intro'],
                responseHint: $viewData['response_hint'],
                phoneDisplay: $viewData['phone_display'],
            ));

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
