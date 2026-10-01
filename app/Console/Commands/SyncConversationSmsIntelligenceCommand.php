<?php

namespace App\Console\Commands;

use App\Ark\Operations\Communications\ConversationSmsIntelligenceAnalyzer;
use App\Ark\Operations\Communications\ConversationSmsIntelligenceSlice;
use App\Ark\Operations\Communications\ConversationSmsIntelligenceSliceTouch;
use App\Ark\Operations\Communications\ConversationSmsIntelligenceTranscriptBuilder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncConversationSmsIntelligenceCommand extends Command
{
    protected $signature = 'communications:sync-sms-intelligence
        {--days=30 : Backfill slices for the last N days}
        {--queue-analysis : Queue analysis for eligible slices without ready analysis}';

    protected $description = 'Build SMS intelligence slices from conversation messages and optionally queue analysis';

    public function handle(
        ConversationSmsIntelligenceTranscriptBuilder $transcriptBuilder,
        ConversationSmsIntelligenceAnalyzer $analyzer,
    ): int {
        $timezone = ShopDisplayTimezone::resolve();
        $days = max(1, (int) $this->option('days'));
        $from = Carbon::now($timezone)->subDays($days)->startOfDay()->utc();
        $to = Carbon::now($timezone)->endOfDay()->utc();

        $messages = ConversationMessage::query()
            ->where('channel', OperationalCommunicationChannel::Sms)
            ->whereBetween('occurred_at', [$from, $to])
            ->orderBy('occurred_at')
            ->get(['conversation_id', 'occurred_at']);

        $groups = $messages->groupBy(function (ConversationMessage $message) use ($timezone): string {
            $activityDate = ($message->occurred_at ?? now())->timezone($timezone)->toDateString();

            return $message->conversation_id.'|'.$activityDate;
        });

        $slices = 0;
        $queued = 0;

        foreach ($groups as $groupKey => $groupMessages) {
            [$conversationId, $activityDate] = explode('|', (string) $groupKey, 2);

            $built = $transcriptBuilder->forConversationDay((int) $conversationId, $activityDate);

            if ($built['message_count'] < 1) {
                continue;
            }

            $slice = ConversationSmsIntelligenceSlice::query()
                ->where('conversation_id', (int) $conversationId)
                ->whereDate('activity_date', $activityDate)
                ->first();

            if ($slice === null) {
                $slice = ConversationSmsIntelligenceSlice::query()->create([
                    'conversation_id' => (int) $conversationId,
                    'activity_date' => $activityDate,
                    'last_message_at' => $built['last_message_at'] ?? now(),
                    'message_count' => $built['message_count'],
                    'inbound_count' => $built['inbound_count'],
                    'outbound_count' => $built['outbound_count'],
                    'transcript' => $built['transcript'] !== '' ? $built['transcript'] : null,
                ]);
            } else {
                $slice->forceFill([
                    'last_message_at' => $built['last_message_at'] ?? $slice->last_message_at,
                    'message_count' => $built['message_count'],
                    'inbound_count' => $built['inbound_count'],
                    'outbound_count' => $built['outbound_count'],
                    'transcript' => $built['transcript'] !== '' ? $built['transcript'] : null,
                ])->saveQuietly();
            }

            $slices++;

            if ($this->option('queue-analysis')) {
                $queued += $this->queueSliceIfNeeded($analyzer, $slice->fresh());
            }
        }

        if ($this->option('queue-analysis')) {
            ConversationSmsIntelligenceSlice::query()
                ->whereBetween('last_message_at', [$from, $to])
                ->where(function ($query): void {
                    $query->whereNull('analysis_status')
                        ->orWhere('analysis_status', CallSessionAnalysisStatus::Failed);
                })
                ->orderBy('id')
                ->each(function (ConversationSmsIntelligenceSlice $slice) use ($analyzer, &$queued): void {
                    $queued += $this->queueSliceIfNeeded($analyzer, $slice);
                });

            ConversationSmsIntelligenceSlice::query()
                ->whereBetween('last_message_at', [$from, $to])
                ->where('analysis_status', CallSessionAnalysisStatus::Ready)
                ->where('analysis_json->follow_up_needed', true)
                ->where(function ($query): void {
                    $query->whereNull('analysis_json->suggested_reply')
                        ->orWhere('analysis_json->suggested_reply', '');
                })
                ->orderBy('id')
                ->each(function (ConversationSmsIntelligenceSlice $slice) use ($analyzer, &$queued): void {
                    $slice->forceFill([
                        'analysis_status' => CallSessionAnalysisStatus::Pending,
                    ])->saveQuietly();

                    $queued += $this->queueSliceIfNeeded($analyzer, $slice->fresh());
                });
        }

        $this->info("Synced {$slices} SMS intelligence slice(s).");

        if ($this->option('queue-analysis')) {
            $this->info("Queued {$queued} slice(s) for analysis.");
        }

        return self::SUCCESS;
    }

    private function queueSliceIfNeeded(
        ConversationSmsIntelligenceAnalyzer $analyzer,
        ConversationSmsIntelligenceSlice $slice,
    ): int {
        if (! ConversationSmsIntelligenceSliceTouch::isEligible($slice)) {
            return 0;
        }

        $before = $slice->analysis_status;
        $analyzer->queueIfEligible($slice->fresh());

        return $slice->fresh()->analysis_status !== $before ? 1 : 0;
    }
}
