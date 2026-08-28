<?php

namespace App\Console\Commands;

use App\Ark\Operations\Leads\Public\PublicLeadFunnelSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PublicLeadFunnelObservationCommand extends Command
{
    protected $signature = 'ark:public-lead-funnel-observation
        {--days=7 : Look back N complete days ending today}
        {--from= : Start date (Y-m-d)}
        {--to= : End date (Y-m-d, inclusive)}
        {--markdown : Output as markdown}';

    protected $description = 'Notebook query — homepage visitors through lead handling (contacted, scheduled, arrived).';

    public function handle(PublicLeadFunnelSummary $funnel): int
    {
        if (! Schema::hasTable('public_surface_events')) {
            $this->components->warn('public_surface_events is not migrated yet.');

            return self::FAILURE;
        }

        [$from, $to] = $this->resolvePeriod();

        $summary = $funnel->forPeriod($from, $to);

        if ($this->option('markdown')) {
            $this->renderMarkdown($summary);

            return self::SUCCESS;
        }

        $this->components->info('Public lead funnel observation');
        $this->line("Period: {$summary['period_from']} → {$summary['period_to']}");
        $this->newLine();

        $rows = $this->funnelRows($summary);
        $this->table(['Stage', 'Count', 'Step rate'], $rows);

        $this->newLine();
        $this->line('Abandoned after start: '.$summary['abandoned_after_start']);
        $this->line('Call clicks: '.$summary['call_clicks'].' · Text clicks: '.$summary['text_clicks']);

        if ($summary['attribution'] !== []) {
            $this->newLine();
            $this->line('Visitor attribution:');
            foreach ($summary['attribution'] as $source => $count) {
                $this->line("  {$source}: {$count}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(): array
    {
        if ($this->option('from')) {
            $from = Carbon::parse($this->option('from'))->startOfDay();
            $to = $this->option('to')
                ? Carbon::parse($this->option('to'))->endOfDay()
                : now()->endOfDay();

            return [$from, $to];
        }

        $days = max(1, (int) $this->option('days'));
        $to = now()->endOfDay();
        $from = now()->subDays($days - 1)->startOfDay();

        return [$from, $to];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<array{0: string, 1: int|string, 2: string}>
     */
    private function funnelRows(array $summary): array
    {
        $rates = $summary['step_rates'];

        return [
            ['Visitors', $summary['visitors'], '—'],
            ['Lead started', $summary['lead_started'], $this->formatRate($rates['visitor_to_start'], 'visitors')],
            ['Lead submitted', $summary['lead_submitted'], $this->formatRate($rates['start_to_submit'], 'starts')],
            ['Leads created', $summary['leads_created'], $this->formatRate($rates['submit_to_created'], 'submits')],
            ['Contacted', $summary['contacted'], $this->formatRate($rates['created_to_contacted'], 'leads')],
            ['Scheduled', $summary['scheduled'], $this->formatRate($rates['contacted_to_scheduled'], 'contacted')],
            ['Arrived', $summary['arrived'], $this->formatRate($rates['scheduled_to_arrived'], 'scheduled')],
        ];
    }

    private function formatRate(?float $rate, string $priorStage): string
    {
        if ($rate === null) {
            return '—';
        }

        return sprintf('%.1f%% of %s', $rate, $priorStage);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function renderMarkdown(array $summary): void
    {
        $this->line('# Public lead funnel observation');
        $this->line('');
        $this->line("Period: **{$summary['period_from']}** → **{$summary['period_to']}**");
        $this->line('');
        $this->line('| Stage | Count | Step rate |');
        $this->line('| --- | ---: | --- |');

        foreach ($this->funnelRows($summary) as [$stage, $count, $rate]) {
            $this->line("| {$stage} | {$count} | {$rate} |");
        }

        $this->line('');
        $this->line('Abandoned after start: '.$summary['abandoned_after_start']);
        $this->line('Call clicks: '.$summary['call_clicks'].' · Text clicks: '.$summary['text_clicks']);

        if ($summary['attribution'] !== []) {
            $this->line('');
            $this->line('## Visitor attribution');
            foreach ($summary['attribution'] as $source => $count) {
                $this->line("- {$source}: {$count}");
            }
        }
    }
}
