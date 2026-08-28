<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\LeadSource;
use Illuminate\Support\Carbon;

/**
 * Read-only homepage → lead handling funnel for notebook observation.
 */
class PublicLeadFunnelSummary
{
    public function __construct(
        private readonly PublicSurfaceEventSummary $surfaceEvents,
    ) {}

    /**
     * @return array{
     *     period_from: string,
     *     period_to: string,
     *     visitors: int,
     *     lead_started: int,
     *     lead_submitted: int,
     *     leads_created: int,
     *     contacted: int,
     *     scheduled: int,
     *     arrived: int,
     *     abandoned_after_start: int,
     *     call_clicks: int,
     *     text_clicks: int,
     *     attribution: array<string, int>,
     *     step_rates: array<string, float|null>,
     * }
     */
    public function forPeriod(Carbon $from, Carbon $to): array
    {
        $surface = $this->surfaceEvents->forPeriod($from, $to);

        $websiteLeads = Lead::query()
            ->notSpam()
            ->where('source', LeadSource::Website)
            ->whereBetween('created_at', [$from, $to]);

        $leadsCreated = (int) (clone $websiteLeads)->count();
        $contacted = (int) (clone $websiteLeads)->whereNotNull('first_contacted_at')->count();
        $scheduled = (int) (clone $websiteLeads)->whereNotNull('scheduled_at')->count();
        $arrived = (int) (clone $websiteLeads)->whereNotNull('arrived_at')->count();

        $visitors = $surface['visitors'];
        $leadStarted = $surface['lead_starts'];
        $leadSubmitted = $surface['lead_submissions'];

        return [
            'period_from' => $from->toDateString(),
            'period_to' => $to->toDateString(),
            'visitors' => $visitors,
            'lead_started' => $leadStarted,
            'lead_submitted' => $leadSubmitted,
            'leads_created' => $leadsCreated,
            'contacted' => $contacted,
            'scheduled' => $scheduled,
            'arrived' => $arrived,
            'abandoned_after_start' => $surface['abandoned_after_start'],
            'call_clicks' => $surface['call_clicks'],
            'text_clicks' => $surface['text_clicks'],
            'attribution' => $surface['attribution'],
            'step_rates' => [
                'visitor_to_start' => $this->stepRate($visitors, $leadStarted),
                'start_to_submit' => $this->stepRate($leadStarted, $leadSubmitted),
                'submit_to_created' => $this->stepRate($leadSubmitted, $leadsCreated),
                'created_to_contacted' => $this->stepRate($leadsCreated, $contacted),
                'contacted_to_scheduled' => $this->stepRate($contacted, $scheduled),
                'scheduled_to_arrived' => $this->stepRate($scheduled, $arrived),
                'visitor_to_arrived' => $this->stepRate($visitors, $arrived),
            ],
        ];
    }

    private function stepRate(int $from, int $to): ?float
    {
        if ($from <= 0) {
            return null;
        }

        return round(($to / $from) * 100, 1);
    }
}
