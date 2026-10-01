<?php

namespace App\Console\Commands;

use App\Ark\Runtime\Exceptions\ExceptionReportArchive;
use Illuminate\Console\Command;

class RecentReportedErrorsCommand extends Command
{
    protected $signature = 'errors:recent
                            {--limit=10 : Number of recent reports to list}
                            {--id= : Show one archived report by report id}
                            {--show= : Show one archived report by filename}';

    protected $description = 'List or show structured site error reports from storage/logs/reported-errors';

    public function handle(ExceptionReportArchive $archive): int
    {
        if (filled($this->option('id'))) {
            return $this->showReportById($archive, (string) $this->option('id'));
        }

        if (filled($this->option('show'))) {
            return $this->showReport($archive, (string) $this->option('show'));
        }

        $recent = $archive->recent((int) $this->option('limit'));

        if ($recent === []) {
            $this->info('No reported errors archived yet.');
            $this->line('Folder: '.$archive->directory());

            return self::SUCCESS;
        }

        $this->table(
            ['When (UTC)', 'ID', 'File', 'Exception', 'Message', 'URL'],
            collect($recent)->map(fn (array $row): array => [
                $row['reported_at'] ?? '',
                $row['id'] ?? '',
                $row['filename'] ?? '',
                class_basename((string) ($row['exception_class'] ?? '')),
                str($row['exception_message'] ?? '')->limit(60)->value(),
                str($row['url'] ?? '')->limit(50)->value(),
            ])->all(),
        );

        $this->newLine();
        $this->line('Folder: '.$archive->directory());
        $this->line('Latest: latest.json');
        $this->line('Detail: php artisan errors:recent --id=<report-id>');
        $this->line('Detail: php artisan errors:recent --show=<filename>');

        return self::SUCCESS;
    }

    private function showReport(ExceptionReportArchive $archive, string $filename): int
    {
        $report = $archive->read($filename);

        if ($report === null) {
            $this->error('Report not found: '.$filename);

            return self::FAILURE;
        }

        $this->renderReport($report);

        return self::SUCCESS;
    }

    private function showReportById(ExceptionReportArchive $archive, string $reportId): int
    {
        $report = $archive->findById($reportId);

        if ($report === null) {
            $this->error('Report not found for id: '.$reportId);

            return self::FAILURE;
        }

        $this->renderReport($report);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderReport(array $report): void
    {
        if (filled($report['report_markdown'] ?? null)) {
            $this->line((string) $report['report_markdown']);

            return;
        }

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
