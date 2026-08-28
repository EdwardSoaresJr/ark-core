<?php

namespace App\Ark\Growth\Listeners;

use App\Ark\Growth\Contracts\Operations\LeadConvertedForGrowth;
use App\Ark\Growth\Sessions\GrowthSessionResolver;
use App\Ark\Operations\Leads\Lead;

final class LinkGrowthSessionOnLeadConversion
{
    public function __construct(
        private readonly GrowthSessionResolver $sessions,
    ) {}

    public function handle(LeadConvertedForGrowth $event): void
    {
        if (! config('growth.enabled', true)) {
            return;
        }

        $lead = Lead::query()->find($event->payload->leadId);

        if ($lead === null || $lead->growth_session_id === null) {
            return;
        }

        $session = $lead->growthSession;

        if ($session === null) {
            return;
        }

        $this->sessions->linkRepairOrder($session, $event->payload->repairOrderId);

        if ($event->payload->conversationId !== null) {
            $this->sessions->linkConversation($session, $event->payload->conversationId);
        }
    }
}
