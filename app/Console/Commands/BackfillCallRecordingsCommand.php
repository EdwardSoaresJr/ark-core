<?php

namespace App\Console\Commands;

use App\Ark\Operations\Telephony\AttachCallRecordingAction;
use App\Ark\Operations\Telephony\TwilioVoiceApi;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class BackfillCallRecordingsCommand extends Command
{
    protected $signature = 'ark:telephony:backfill-recordings
        {--since= : UTC/local datetime; default 48 hours ago}
        {--dry-run : List matches without writing}';

    protected $description = 'Attach Twilio recordings onto existing CallSession rows that are missing media.';

    public function handle(TwilioVoiceApi $twilio, AttachCallRecordingAction $attach): int
    {
        if (! $twilio->configured()) {
            $this->error('Twilio is not configured.');

            return self::FAILURE;
        }

        $since = filled($this->option('since'))
            ? Carbon::parse((string) $this->option('since'))
            : now()->subHours(48);
        $dryRun = (bool) $this->option('dry-run');

        $recordings = $twilio->listRecordingsSince($since);
        $attached = 0;
        $skipped = 0;
        $unmatched = 0;

        foreach ($recordings as $recording) {
            if (($recording['status'] ?? '') !== '' && $recording['status'] !== 'completed') {
                $skipped++;

                continue;
            }

            $session = $attach->findSession($recording['call_sid']);

            if ($session === null) {
                $unmatched++;

                continue;
            }

            if (filled($session->recording_url)
                || $session->recording_sid === $recording['sid']
                || $session->voicemail_sid === $recording['sid']
            ) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $attached++;

                continue;
            }

            $attach->attach(
                $session,
                $recording['url'],
                $recording['duration'],
                $recording['sid'],
                voicemail: false,
            );
            $attached++;
        }

        $this->info(
            ($dryRun ? 'Would attach ' : 'Attached ').$attached
            .' recording'.($attached === 1 ? '' : 's')
            .' ('.$skipped.' skipped, '.$unmatched.' unmatched). Since '.$since->toDateTimeString().'.'
        );

        return self::SUCCESS;
    }
}
