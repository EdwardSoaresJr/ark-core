<?php

namespace App\Ark\Operations\Leads;

use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationParticipantType;
use App\Ark\Operations\Leads\WebsiteLeadInterruptBroadcaster;
use App\Models\User;

/**
 * Records first advisor outbound on a lead conversation for median first-response measurement.
 */
final class RecordLeadFirstContactAction
{
    public function execute(ConversationMessage $message, User $actor): void
    {
        if ($message->direction !== OperationalCommunicationDirection::Outbound) {
            return;
        }

        $message->loadMissing('participant');

        if ($message->participant?->participant_type !== ConversationParticipantType::Advisor) {
            return;
        }

        if (($message->metadata['advisor_note'] ?? false) === true
            || ($message->metadata['call_note'] ?? false) === true) {
            return;
        }

        $leadIds = Lead::query()
            ->open()
            ->notSpam()
            ->where('conversation_id', $message->conversation_id)
            ->whereNull('first_contacted_at')
            ->pluck('id');

        if ($leadIds->isEmpty()) {
            return;
        }

        $contactedAt = $message->occurred_at ?? now();

        Lead::query()
            ->whereIn('id', $leadIds)
            ->update(['first_contacted_at' => $contactedAt]);

        $broadcaster = app(WebsiteLeadInterruptBroadcaster::class);

        foreach ($leadIds as $leadId) {
            $broadcaster->clearForLead((int) $leadId);
        }

        $audit = app(LeadConfirmationAuditConversation::class);

        foreach (Lead::query()->whereIn('id', $leadIds)->get() as $lead) {
            $audit->resolveSiblingAuditsForLead($lead);
        }
    }
}
