<?php

namespace App\Console\Commands;

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionAnalyzer;
use Illuminate\Console\Command;

class AnalyzeCallSessionsCommand extends Command
{
    protected $signature = 'ark:analyze-call-sessions
        {--limit=50 : Maximum calls to queue}
        {--force : Re-queue calls that already have analysis}';

    protected $description = 'Queue AI transcription and analysis for call recordings.';

    public function handle(CallSessionAnalyzer $analyzer): int
    {
        if (! CallSessionAnalyzer::enabled()) {
            $this->error('OpenAI API key is not configured in Settings → Communications → Recording.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $force = (bool) $this->option('force');

        $query = CallSession::query()
            ->where(function ($scoped): void {
                $scoped->whereNotNull('recording_url')
                    ->orWhereNotNull('voicemail_url');
            })
            ->orderByDesc('started_at');

        if (! $force) {
            $query->where(function ($scoped): void {
                $scoped->whereNull('analysis_status')
                    ->orWhereIn('analysis_status', ['failed']);
            });
        }

        $queued = 0;

        foreach ($query->limit($limit)->cursor() as $callSession) {
            if ($force) {
                $callSession->forceFill([
                    'analysis_status' => null,
                    'analysis_error' => null,
                ])->saveQuietly();
            }

            $analyzer->queueIfEligible($callSession->fresh());
            $queued++;
        }

        $this->info("Queued {$queued} call session(s) for analysis.");

        return self::SUCCESS;
    }
}
