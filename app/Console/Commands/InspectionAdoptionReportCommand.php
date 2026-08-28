<?php

namespace App\Console\Commands;

use App\Ark\Operations\Inspections\InspectionAdoptionReport;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class InspectionAdoptionReportCommand extends Command
{
    protected $signature = 'ark:inspection-adoption
        {--days= : Limit recorded activity to the last N days}
        {--since= : Activity start (overrides --days when paired with --until)}
        {--until= : Activity end (defaults to now)}
        {--markdown : Print markdown notebook report}';

    protected $description = 'Print internal inspection adoption facts for floor observation (staff only).';

    public function handle(InspectionAdoptionReport $report): int
    {
        [$activitySince, $activityUntil] = $this->resolveActivityWindow();

        $data = $report->snapshot($activitySince, $activityUntil);

        if ($this->option('markdown')) {
            $this->line($report->toMarkdown($data));

            return self::SUCCESS;
        }

        $this->components->info('Inspection adoption audit');
        $this->line('Generated: '.$data['generated_at']);

        if ($data['activity_window'] !== null) {
            $this->line('Activity window: '.$data['activity_window']['since'].' → '.$data['activity_window']['until']);
        } else {
            $this->line('Activity window: all recorded facts on open repair orders');
        }

        $this->newLine();
        $this->components->info('Open repair orders');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total open', (string) $data['open_repair_orders']['total']],
                ['With inspection workspace opened', (string) $data['open_repair_orders']['with_inspection']],
                ['Without inspection', (string) $data['open_repair_orders']['without_inspection']],
                ['Engaged (≥1 recorded item)', (string) $data['open_repair_orders']['engaged']],
            ],
        );

        $this->newLine();
        $this->components->info('Averages per opened inspection');
        $this->table(
            ['Metric', 'Average'],
            [
                ['Inspections opened on open ROs', (string) $data['averages']['inspections']],
                ['Recorded items', (string) $data['averages']['recorded_items']],
                ['Measurements', (string) $data['averages']['measurements']],
                ['Photos', (string) $data['averages']['photos']],
            ],
        );

        $this->newLine();
        $this->components->info('Categories (recorded items, most → least)');
        $this->table(
            ['Category', 'Recorded items'],
            collect($data['categories']['most_used'])->map(fn (array $row): array => [
                $row['category'],
                (string) $row['recorded_items'],
            ])->all(),
        );

        $this->newLine();
        $this->components->info('Friction signals');
        $this->table(
            ['Signal', 'Count'],
            [
                ['Notes only (no measurement or photo)', (string) $data['friction_signals']['notes_only_items']],
                ['Items with measurements', (string) $data['friction_signals']['items_with_measurements']],
                ['Items with photos', (string) $data['friction_signals']['items_with_photos']],
                ['State only (no notes, measurement, or photo)', (string) $data['friction_signals']['state_only_items']],
            ],
        );

        $this->newLine();
        $this->comment('Internal staff report only. No customer-facing dashboard.');
        $this->comment('Notebook copy: php artisan ark:inspection-adoption --markdown');

        return self::SUCCESS;
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveActivityWindow(): array
    {
        $until = filled($this->option('until'))
            ? Carbon::parse((string) $this->option('until'))
            : now();

        if (filled($this->option('since'))) {
            return [Carbon::parse((string) $this->option('since')), $until];
        }

        if ($this->option('days') !== null) {
            $days = max(1, (int) $this->option('days'));

            return [$until->copy()->subDays($days), $until];
        }

        return [null, null];
    }
}
