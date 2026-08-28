<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopIntegrationCredentials;
use App\Ark\Operations\Telephony\CallSessionAnalysisStatus;
use App\Ark\Operations\Telephony\CallSessionAnalyzer;
use Illuminate\Support\Str;

final class ConversationSmsIntelligenceAnalyzer
{
    public function __construct(
        private readonly ConversationSmsIntelligenceTranscriptBuilder $transcriptBuilder,
        private readonly CommunicationInteractionAnalysisSummarizer $summarizer,
        private readonly ShopIntegrationCredentials $credentials,
    ) {}

    public static function enabled(): bool
    {
        return CallSessionAnalyzer::enabled();
    }

    public function queueIfEligible(ConversationSmsIntelligenceSlice $slice): void
    {
        if (! $this->credentials->openaiConfigured()) {
            return;
        }

        if (! ConversationSmsIntelligenceSliceTouch::isEligible($slice)) {
            return;
        }

        if ($slice->analysis_status === CallSessionAnalysisStatus::Ready) {
            return;
        }

        $slice->forceFill([
            'analysis_status' => CallSessionAnalysisStatus::Pending,
            'analysis_error' => null,
        ])->saveQuietly();

        AnalyzeConversationSmsIntelligenceSliceJob::dispatch($slice->id);
    }

    public function analyze(ConversationSmsIntelligenceSlice $slice): void
    {
        if (! $this->credentials->openaiConfigured()) {
            $slice->forceFill([
                'analysis_status' => CallSessionAnalysisStatus::Skipped,
                'analysis_error' => 'OpenAI API key is not configured in shop settings.',
            ])->saveQuietly();

            return;
        }

        if (! ConversationSmsIntelligenceSliceTouch::isEligible($slice)) {
            $slice->forceFill([
                'analysis_status' => CallSessionAnalysisStatus::Skipped,
                'analysis_error' => 'Thread does not meet SMS intelligence eligibility.',
            ])->saveQuietly();

            return;
        }

        $slice->forceFill([
            'analysis_status' => CallSessionAnalysisStatus::Processing,
            'analysis_error' => null,
        ])->saveQuietly();

        try {
            $built = $this->transcriptBuilder->forConversationDay(
                (int) $slice->conversation_id,
                $slice->activity_date->toDateString(),
            );

            $transcript = trim($built['transcript']);

            if ($transcript === '') {
                throw new \RuntimeException('SMS transcript is empty.');
            }

            $analysis = $this->summarizer->summarize('sms', $this->contextFor($slice), $transcript);

            $slice->forceFill([
                'transcript' => $transcript,
                'message_count' => $built['message_count'],
                'inbound_count' => $built['inbound_count'],
                'outbound_count' => $built['outbound_count'],
                'last_message_at' => $built['last_message_at'] ?? $slice->last_message_at,
                'analysis_json' => $analysis,
                'analysis_status' => CallSessionAnalysisStatus::Ready,
                'analysis_error' => null,
                'analyzed_at' => now(),
            ])->saveQuietly();

            app(RecordCommunicationReviewFromSmsSliceAction::class)->execute($slice->fresh());
        } catch (\Throwable $exception) {
            $slice->forceFill([
                'analysis_status' => CallSessionAnalysisStatus::Failed,
                'analysis_error' => Str::limit($exception->getMessage(), 500),
            ])->saveQuietly();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function contextFor(ConversationSmsIntelligenceSlice $slice): array
    {
        $slice->loadMissing([
            'conversation.owner',
            'conversation.participants.customer',
            'conversation.links',
        ]);

        $conversation = $slice->conversation;
        $customer = $conversation?->participants
            ->first(fn ($participant) => $participant->customer_id !== null)
            ?->customer;

        $repairOrder = $conversation?->links
            ->first(fn ($link) => $link->linkable_type === RepairOrder::class)
            ?->linkable;

        return [
            'channel' => 'sms',
            'thread_shape' => $this->threadShape($slice),
            'activity_date' => $slice->activity_date->toDateString(),
            'message_count' => $slice->message_count,
            'inbound_count' => $slice->inbound_count,
            'outbound_count' => $slice->outbound_count,
            'customer_name' => $customer instanceof Customer ? $customer->name : null,
            'customer_type' => $customer instanceof Customer ? $customer->customer_type : null,
            'staff_owner' => $conversation?->owner?->name,
            'repair_order_number' => $repairOrder instanceof RepairOrder ? $repairOrder->repair_order_id : null,
            'contact_phone' => PhoneNumber::display($conversation?->contact_address) ?? $conversation?->contact_address,
        ];
    }

    private function threadShape(ConversationSmsIntelligenceSlice $slice): string
    {
        if ($slice->inbound_count >= 1 && $slice->outbound_count >= 1) {
            return 'two_way';
        }

        if ($slice->outbound_count >= 1 && $slice->inbound_count === 0) {
            return 'outbound_only';
        }

        if ($slice->inbound_count >= 1 && $slice->outbound_count === 0) {
            return 'inbound_only';
        }

        return 'unknown';
    }
}
