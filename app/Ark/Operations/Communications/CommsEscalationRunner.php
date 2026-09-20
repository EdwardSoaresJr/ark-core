<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use App\Ark\Operations\Messaging\OutboundSmsTransport;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionQueue;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class CommsEscalationRunner
{
    public function __construct(
        private readonly CallSessionQueue $callQueue,
        private readonly UnreadInboundMessageQueue $unreadMessages,
    ) {}

    public function run(): int
    {
        $settings = CommsPressureSettings::fromShopSettings();
        $credentials = ShopIntegrationCredentials::forCurrentShop();

        if (! $settings->escalationEnabled() || ! $credentials->twilioConfigured()) {
            return 0;
        }

        $sender = app(OutboundSmsTransport::class);
        $delayMinutes = $settings->escalationDelayMinutes();
        $cutoff = now()->subMinutes($delayMinutes);
        $sent = 0;

        $this->callQueue->reconcileStaleLiveSessions();

        foreach ($this->callQueue->waitingSessions() as $session) {
            if ($this->shouldEscalateCall($session, $cutoff) && $this->notifyAdvisors($sender, $this->callEscalationBody($session), 'call', (string) $session->id)) {
                $sent++;
            }
        }

        $viewer = $this->advisorsWithPhones()->first();

        if ($viewer !== null) {
            foreach ($this->unreadMessages->latestUnreadPerConversation($viewer) as $message) {
                if ($this->shouldEscalateMessage($message, $cutoff) && $this->notifyAdvisors($sender, $this->messageEscalationBody($message), 'message', (string) $message->id)) {
                    $sent++;
                }
            }
        }

        foreach ($this->uncontactedWebsiteLeads($cutoff) as $lead) {
            if ($this->shouldEscalateLead($lead, $cutoff) && $this->notifyAdvisors($sender, $this->websiteLeadEscalationBody($lead), 'website_lead', (string) $lead->id)) {
                $sent++;
            }
        }

        return $sent;
    }

    private function shouldEscalateCall(CallSession $session, Carbon $cutoff): bool
    {
        if ($session->started_at === null || $session->started_at->greaterThan($cutoff)) {
            return false;
        }

        if ($this->alreadyEscalated('call', (string) $session->id)) {
            return false;
        }

        return in_array($session->status, [
            CallSessionStatus::Ringing,
            CallSessionStatus::Missed,
            CallSessionStatus::Answered,
            CallSessionStatus::Completed,
            CallSessionStatus::Failed,
        ], true);
    }

    private function shouldEscalateMessage(ConversationMessage $message, Carbon $cutoff): bool
    {
        if ($message->occurred_at === null || $message->occurred_at->greaterThan($cutoff)) {
            return false;
        }

        return ! $this->alreadyEscalated('message', (string) $message->id);
    }

    private function shouldEscalateLead(Lead $lead, Carbon $cutoff): bool
    {
        if ($lead->created_at->greaterThan($cutoff)) {
            return false;
        }

        return ! $this->alreadyEscalated('website_lead', (string) $lead->id);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Lead>
     */
    private function uncontactedWebsiteLeads(Carbon $cutoff)
    {
        return Lead::query()
            ->open()
            ->notSpam()
            ->notContacted()
            ->where('source', LeadSource::Website)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('created_at')
            ->limit(25)
            ->get();
    }

    private function callEscalationBody(CallSession $session): string
    {
        $phone = PhoneNumber::display($session->normalized_from ?? $session->from_number) ?: 'Unknown caller';
        $customer = $session->customer?->name;
        $label = filled($customer) ? "{$customer} ({$phone})" : $phone;
        $attentionUrl = Route::has('operations.communications.inbox')
            ? CommunicationsNeedsYou::url()
            : '';

        return trim("ARK: Unhandled call from {$label}. Open Communications now. {$attentionUrl}");
    }

    private function messageEscalationBody(ConversationMessage $message): string
    {
        $customer = $message->participant?->customer?->full_name ?? 'Customer';
        $snippet = mb_substr(trim((string) $message->body), 0, 80);
        $snippet = $snippet !== '' ? " \"{$snippet}\"" : '';
        $attentionUrl = Route::has('operations.communications.inbox')
            ? CommunicationsNeedsYou::url()
            : '';

        return trim("ARK: Unread text from {$customer}{$snippet}. Open Communications now. {$attentionUrl}");
    }

    private function websiteLeadEscalationBody(Lead $lead): string
    {
        $name = filled($lead->contact_name) ? trim((string) $lead->contact_name) : 'Website lead';
        $phone = $lead->display_phone ?: 'No phone';
        $snippet = mb_substr(trim($lead->concern), 0, 80);
        $snippet = $snippet !== '' ? " \"{$snippet}\"" : '';
        $intakeUrl = Route::has('operations.leads.intake')
            ? route('operations.leads.intake', $lead)
            : CommunicationsNeedsYou::url(
                $lead->conversation_id !== null ? ['conversation' => $lead->conversation_id] : []
            );

        return trim("ARK: Unhandled website lead from {$name} ({$phone}){$snippet}. Check In now. {$intakeUrl}");
    }

    private function notifyAdvisors(OutboundSmsTransport $sender, string $body, string $kind, string $referenceId): bool
    {
        $recipients = $this->advisorsWithPhones();

        if ($recipients->isEmpty()) {
            return false;
        }

        $delivered = false;

        foreach ($recipients as $advisor) {
            $phone = (string) $advisor->phone;

            if ($phone === '') {
                continue;
            }

            try {
                $sender->send($phone, $body);
                $delivered = true;
            } catch (\Throwable $exception) {
                Log::warning('comms escalation SMS failed', [
                    'advisor_id' => $advisor->id,
                    'kind' => $kind,
                    'reference_id' => $referenceId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($delivered) {
            $this->markEscalated($kind, $referenceId);
        }

        return $delivered;
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function advisorsWithPhones()
    {
        return User::query()
            ->active()
            ->role(ArkRole::Advisor->value)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get();
    }

    private function alreadyEscalated(string $kind, string $referenceId): bool
    {
        return Cache::has($this->cacheKey($kind, $referenceId));
    }

    private function markEscalated(string $kind, string $referenceId): void
    {
        Cache::put(
            $this->cacheKey($kind, $referenceId),
            true,
            now()->addMinutes(CommsPressureSettings::fromShopSettings()->escalationCooldownMinutes()),
        );
    }

    private function cacheKey(string $kind, string $referenceId): string
    {
        return "comms:escalated:{$kind}:{$referenceId}";
    }
}
