<?php

namespace App\Ark\Operations\Leads;

use Illuminate\Support\Str;

final class WebsiteLeadInterruptPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function forLead(Lead $lead): array
    {
        $headline = filled($lead->contact_name) ? trim((string) $lead->contact_name) : 'Unknown';
        $vehicle = $lead->roughVehicleLabel();

        return [
            'kind' => 'website_lead',
            'queue_tab' => 'portal',
            'channel' => 'website',
            'channel_label' => 'Website Lead',
            'direction' => 'inbound',
            'direction_label' => 'Inbound',
            'state' => 'unread',
            'state_label' => 'New lead',
            'lead_id' => $lead->id,
            'lead_interrupt_key' => 'lead:'.$lead->id,
            'conversation_id' => $lead->conversation_id,
            'headline' => $headline,
            'display_phone' => $lead->display_phone ?: '—',
            'snippet' => Str::limit(trim($lead->concern), 140),
            'matched' => $lead->customer_id !== null,
            'customer_id' => $lead->customer_id,
            'context_summary' => trim(implode(' · ', array_filter([
                $vehicle,
                $lead->contact_preference?->outreachLabel(),
            ]))),
            'intake_url' => route('operations.leads.intake', $lead),
            'leads_url' => \App\Ark\Operations\Communications\CommunicationsNeedsYou::url(),
            'create_contact_url' => IngressCreateContactUrl::forLead($lead),
            'reply_url' => null,
            'lookup_url' => null,
            'show_reply_action' => false,
            'show_mark_read_action' => false,
            'priority' => 'high',
            'dropdown_label' => 'Website Lead · '.$headline,
        ];
    }
}
