<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerSmsSendEligibility;
use App\Ark\Operations\Messaging\ResolvePhoneSmsCapabilityAction;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendMissedCallRescueSmsAction
{
    public function __construct(
        private readonly OutboundSmsTransport $transport,
        private readonly ConversationRecorder $recorder,
        private readonly ConversationResolver $conversations,
        private readonly InboundCallerDisplayPhone $callerPhone,
        private readonly ResolvePhoneSmsCapabilityAction $smsCapability,
    ) {}

    public function execute(CallSession $session): bool
    {
        if ($session->direction !== CallSessionDirection::Inbound) {
            return false;
        }

        if ($session->status !== CallSessionStatus::Missed) {
            return false;
        }

        $flow = TelephonyCallFlowSettings::fromShopSettings();

        if (! $flow->missedCallRescueEnabled()) {
            return false;
        }

        $credentials = ShopIntegrationCredentials::forCurrentShop();

        if (! $credentials->twilioConfigured()) {
            return false;
        }

        if (! filled(ShopSettings::current()->telephony_inbound_number)) {
            return false;
        }

        $normalizedPhone = $this->callerPhone->normalizedForSession($session)
            ?? PhoneNumber::normalize($session->normalized_from)
            ?? PhoneNumber::normalize($session->from_number);

        if ($normalizedPhone === null || strlen($normalizedPhone) < 10) {
            return false;
        }

        $cooldownKey = $this->cooldownCacheKey($normalizedPhone);

        if (Cache::has($cooldownKey)) {
            return false;
        }

        if ($this->recentRescueExists($normalizedPhone, $flow->missedCallRescueCooldownMinutes())) {
            Cache::put($cooldownKey, true, now()->addMinutes($flow->missedCallRescueCooldownMinutes()));

            return false;
        }

        $customer = $session->customer_id !== null
            ? Customer::query()->find($session->customer_id)
            : Customer::query()->where('phone', $normalizedPhone)->first();

        if ($customer instanceof Customer) {
            $eligibility = CustomerSmsSendEligibility::for($customer, $credentials);

            if ($eligibility->optedOut() || ! $eligibility->canSend()) {
                return false;
            }
        }

        $capability = $this->smsCapability->execute($normalizedPhone);

        if ($capability !== null && ! $capability->sms_capable) {
            Log::info('missed_call_rescue_sms_skipped_not_capable', [
                'call_session_id' => $session->id,
                'phone' => $normalizedPhone,
                'reason' => $capability->reason,
            ]);

            return false;
        }

        $body = MissedCallRescueCopy::bodyFor($session, $flow);

        try {
            $result = $this->transport->send($normalizedPhone, $body);
            $conversation = $this->conversations->forPhone($normalizedPhone);

            $this->recorder->recordSystemOutboundSms(
                $conversation,
                $body,
                $result->messageId,
                metadata: [
                    'missed_call_rescue' => true,
                    'call_session_id' => $session->id,
                ],
            );

            Cache::put($cooldownKey, true, now()->addMinutes($flow->missedCallRescueCooldownMinutes()));

            return true;
        } catch (Throwable $exception) {
            Log::warning('missed_call_rescue_sms_failed', [
                'call_session_id' => $session->id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function cooldownCacheKey(string $normalizedPhone): string
    {
        return 'missed_call_rescue:cooldown:'.$normalizedPhone;
    }

    private function recentRescueExists(string $normalizedPhone, int $cooldownMinutes): bool
    {
        $conversation = $this->conversations->findForPhone($normalizedPhone);

        if ($conversation === null) {
            return false;
        }

        return ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('created_at', '>=', now()->subMinutes($cooldownMinutes))
            ->get(['metadata'])
            ->contains(fn (ConversationMessage $message): bool => ($message->metadata['missed_call_rescue'] ?? false) === true);
    }
}
