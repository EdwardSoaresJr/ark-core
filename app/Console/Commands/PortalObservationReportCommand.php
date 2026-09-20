<?php

namespace App\Console\Commands;

use App\Ark\Operations\Portal\PortalObservationReport;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PortalObservationReportCommand extends Command
{
    protected $signature = 'ark:portal-observation
        {--days=30 : Include facts from the last N days}
        {--since= : Start datetime (overrides --days when paired with --until)}
        {--until= : End datetime (defaults to now)}';

    protected $description = 'Print internal portal observation facts for roadmap decisions (staff only).';

    public function handle(PortalObservationReport $report): int
    {
        [$since, $until] = $this->resolvePeriod();

        $data = $report->forPeriod($since, $until);

        $this->components->info('Portal observation report');
        $this->line("Period: {$data['period']['since']} → {$data['period']['until']}");
        $this->newLine();

        $this->components->info('Portal funnel');
        $this->table(
            ['Step', 'Count', 'Unique sessions', 'Rate of prior step'],
            collect($data['funnel'])->map(fn (array $row): array => [
                $row['step'],
                (string) $row['count'],
                $row['unique_sessions'] !== null ? (string) $row['unique_sessions'] : '—',
                $row['rate_of_prior'] ?? '—',
            ])->all(),
        );
        $this->newLine();

        $this->components->info('Event volume (which signal is most common?)');
        $this->table(
            ['Event', 'Count', 'Unique sessions'],
            collect($data['event_volume'])->map(fn (array $row): array => [
                $row['event'],
                (string) $row['count'],
                (string) $row['unique_sessions'],
            ])->all(),
        );
        $this->newLine();

        $this->components->info('Vehicle surface signals');
        $this->table(
            ['Signal', 'Count', 'Unique sessions'],
            collect($data['vehicle_surface'])->map(fn (array $row): array => [
                $row['signal'],
                (string) $row['count'],
                (string) $row['unique_sessions'],
            ])->all(),
        );
        $this->newLine();

        $this->components->info('Historical vs active vehicle views');
        $this->table(
            ['Context', 'Vehicle views', 'Unique sessions', 'Share of vehicle views'],
            collect($data['historical_vs_active'])->map(fn (array $row): array => [
                $row['context'],
                (string) $row['vehicle_views'],
                (string) $row['unique_sessions'],
                $row['share'],
            ])->all(),
        );

        if ($data['vehicle_concentration'] !== []) {
            $this->newLine();
            $this->components->info('Vehicle concentration (top viewed vehicles)');
            $this->table(
                ['Vehicle', 'Unique sessions', 'Vehicle views'],
                collect($data['vehicle_concentration'])->map(fn (array $row): array => [
                    $row['vehicle'],
                    (string) $row['unique_sessions'],
                    (string) $row['vehicle_views'],
                ])->all(),
            );
        }

        if ($data['document_types'] !== []) {
            $this->newLine();
            $this->components->info('Document types');
            $this->table(
                ['Document type', 'Viewed', 'Downloaded'],
                collect($data['document_types'])->map(fn (array $row): array => [
                    $row['document_type'],
                    (string) $row['viewed'],
                    (string) $row['downloaded'],
                ])->all(),
            );
        }

        $this->newLine();
        $this->comment('Internal staff report only. No customer-facing dashboard.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(): array
    {
        $until = filled($this->option('until'))
            ? Carbon::parse((string) $this->option('until'))
            : now();

        if (filled($this->option('since'))) {
            return [Carbon::parse((string) $this->option('since')), $until];
        }

        $days = max(1, (int) $this->option('days'));

        return [$until->copy()->subDays($days), $until];
    }
}
