<?php

namespace App\Console\Commands;

use App\Ark\Operations\Communications\RecordCommunicationReviewFromCallAction;
use App\Ark\Operations\Communications\RecordCommunicationReviewFromSmsSliceAction;
use App\Ark\Operations\Communications\ConversationSmsIntelligenceSlice;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;
use Illuminate\Console\Command;

class SyncCommunicationReviewsCommand extends Command
{
    protected $signature = 'communications:sync-reviews {--call-session= : Sync one call session id}';

    protected $description = 'Backfill communication_reviews from analyzed calls and SMS threads';

    public function handle(
        RecordCommunicationReviewFromCallAction $callAction,
        RecordCommunicationReviewFromSmsSliceAction $smsAction,
    ): int {
        $callQuery = CallSession::query()->where('analysis_status', CallSessionAnalysisStatus::Ready);

        if ($id = $this->option('call-session')) {
            $callQuery->whereKey((int) $id);
        }

        $synced = 0;

        $callQuery->orderBy('id')->each(function (CallSession $session) use ($callAction, &$synced): void {
            if ($callAction->execute($session) !== null) {
                $synced++;
            }
        });

        ConversationSmsIntelligenceSlice::query()
            ->where('analysis_status', CallSessionAnalysisStatus::Ready)
            ->orderBy('id')
            ->each(function (ConversationSmsIntelligenceSlice $slice) use ($smsAction, &$synced): void {
                if ($smsAction->execute($slice) !== null) {
                    $synced++;
                }
            });

        $this->info("Synced {$synced} communication review(s).");

        return self::SUCCESS;
    }
}
