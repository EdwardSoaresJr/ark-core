<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Communications\CommunicationInteractionAnalysisSummarizer;
use App\Ark\Operations\Communications\RecordCommunicationReviewFromCallAction;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use Illuminate\Support\Str;

final class CallSessionAnalyzer
{
    public const MIN_DURATION_SECONDS = 8;

    public function __construct(
        private readonly CallSessionAudioFetcher $audioFetcher,
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public static function enabled(): bool
    {
        return ShopIntegrationCredentials::forCurrentShop()->openaiConfigured();
    }

    public function queueIfEligible(CallSession $callSession): void
    {
        if (! $this->credentials->openaiConfigured()) {
            return;
        }

        if ($callSession->analysisMediaKind() === null) {
            return;
        }

        $duration = $callSession->analysisDurationSeconds();

        if ($duration !== null && $duration < self::MIN_DURATION_SECONDS) {
            $callSession->forceFill([
                'analysis_status' => CallSessionAnalysisStatus::Skipped,
                'analysis_error' => 'Recording shorter than '.self::MIN_DURATION_SECONDS.' seconds.',
            ])->saveQuietly();

            return;
        }

        if ($callSession->analysis_status === CallSessionAnalysisStatus::Ready) {
            return;
        }

        $callSession->forceFill([
            'analysis_status' => CallSessionAnalysisStatus::Pending,
            'analysis_error' => null,
        ])->saveQuietly();

        AnalyzeCallSessionJob::dispatch($callSession->id);
    }

    public function analyze(CallSession $callSession): void
    {
        if (! $this->credentials->openaiConfigured()) {
            $callSession->forceFill([
                'analysis_status' => CallSessionAnalysisStatus::Skipped,
                'analysis_error' => 'Model provider is not configured.',
            ])->saveQuietly();

            return;
        }

        $mediaKind = $callSession->analysisMediaKind();

        if ($mediaKind === null) {
            $callSession->forceFill([
                'analysis_status' => CallSessionAnalysisStatus::Skipped,
                'analysis_error' => 'No recording or voicemail available.',
            ])->saveQuietly();

            return;
        }

        $callSession->forceFill([
            'analysis_status' => CallSessionAnalysisStatus::Processing,
            'analysis_error' => null,
        ])->saveQuietly();

        try {
            $audio = $this->audioFetcher->fetchMp3($callSession, $mediaKind);

            if ($audio === null) {
                throw new \RuntimeException('Could not download call audio for analysis.');
            }

            $transcript = $this->transcribe($audio, $callSession->id);

            if (trim($transcript) === '') {
                throw new \RuntimeException('Transcription returned empty text.');
            }

            $analysis = app(CommunicationInteractionAnalysisSummarizer::class)
                ->summarize('call', $this->analysisContext($callSession), $transcript);

            $callSession->forceFill([
                'transcript' => $transcript,
                'analysis_json' => $analysis,
                'analysis_status' => CallSessionAnalysisStatus::Ready,
                'analysis_error' => null,
                'analyzed_at' => now(),
            ])->saveQuietly();

            app(RecordCommunicationReviewFromCallAction::class)->execute($callSession->fresh());
        } catch (\Throwable $exception) {
            $callSession->forceFill([
                'analysis_status' => CallSessionAnalysisStatus::Failed,
                'analysis_error' => Str::limit($exception->getMessage(), 500),
            ])->saveQuietly();
        }
    }

    private function transcribe(string $audioBytes, int $callSessionId): string
    {
        throw new \RuntimeException('Model provider is not configured.');
    }

    /**
     * @return array<string, mixed>
     */
    private function analysisContext(CallSession $callSession): array
    {
        $callSession->loadMissing(['customer', 'owner', 'repairOrder']);

        return [
            'direction' => $callSession->directionLabel(),
            'status' => $callSession->status->label(),
            'customer_name' => $callSession->customer?->name,
            'customer_type' => $callSession->customer?->customer_type,
            'staff_owner' => $callSession->owner?->name,
            'repair_order_number' => $callSession->repairOrder?->repair_order_id,
            'duration_seconds' => $callSession->analysisDurationSeconds(),
        ];
    }
}
