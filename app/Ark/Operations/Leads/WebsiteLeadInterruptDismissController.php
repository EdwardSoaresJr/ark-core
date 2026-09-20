<?php

namespace App\Ark\Operations\Leads;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class WebsiteLeadInterruptDismissController
{
    public function __invoke(Request $request, WebsiteLeadInterruptDismissal $dismissal, WebsiteLeadInterruptBroadcaster $broadcaster): Response
    {
        $leadId = $request->integer('lead_id');
        $viewer = $request->user();

        if ($viewer !== null && $leadId > 0) {
            $dismissal->dismiss($viewer->id, $leadId);
        }

        if ($leadId > 0) {
            $broadcaster->clearForLead($leadId);
        }

        return response()->noContent();
    }
}
