<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Reports\OperationalReportDateScope;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Runtime\Ecosystem\EcosystemArkademyBridge;
use Illuminate\Support\Carbon;

final class DailyCoachingDigestProjection
{
    public function __construct(
        private readonly CommunicationIntelligenceIndexQuery $intelligenceQuery,
        private readonly SmsIntelligenceQuery $smsIntelligenceQuery,
    ) {}

    /**
     * @return array{
     *     range_label: string,
     *     strongest_call: array<string, mixed>|null,
     *     coaching_opportunity: array<string, mixed>|null,
     *     call_intelligence_url: string,
     *     arkademy_advisor_calls_url: string,
     *     review_count: int
     * }
     */
    public function forShopDate(?string $date = null): array
    {
        [$from, $to] = filled($date)
            ? OperationalReportDateScope::resolveRange($date, $date)
            : OperationalReportDateScope::resolveRange(
                OperationalReportDateScope::shopNow()->toDateString(),
                OperationalReportDateScope::shopNow()->toDateString(),
            );

        $reviews = CommunicationReview::query()
            ->with([
                'callSession.customer',
                'callSession.owner',
                'callSession.repairOrder',
                'smsSlice.conversation.participants.customer',
                'smsSlice.conversation.owner',
                'advisor',
            ])
            ->whereBetween('reviewed_at', [$from, $to])
            ->get();

        $strongest = $reviews
            ->filter(fn (CommunicationReview $review): bool => $review->composite_score !== null)
            ->sort(function (CommunicationReview $left, CommunicationReview $right): int {
                $scoreCompare = ($right->composite_score ?? 0) <=> ($left->composite_score ?? 0);

                if ($scoreCompare !== 0) {
                    return $scoreCompare;
                }

                return ($right->reviewed_at?->getTimestamp() ?? 0) <=> ($left->reviewed_at?->getTimestamp() ?? 0);
            })
            ->first();

        $coachingOpportunity = $reviews
            ->filter(fn (CommunicationReview $review): bool => $review->coaching_opportunity_weight > 0)
            ->sort(function (CommunicationReview $left, CommunicationReview $right): int {
                $weightCompare = $right->coaching_opportunity_weight <=> $left->coaching_opportunity_weight;

                if ($weightCompare !== 0) {
                    return $weightCompare;
                }

                $leftComposite = $left->composite_score ?? 100;
                $rightComposite = $right->composite_score ?? 100;

                return $leftComposite <=> $rightComposite;
            })
            ->first();

        $timezone = ShopDisplayTimezone::resolve();
        $rangeLabel = Carbon::parse($from)->timezone($timezone)->format('M j, Y');

        return [
            'range_label' => $rangeLabel,
            'strongest_call' => $strongest ? $this->presentReview($strongest) : null,
            'coaching_opportunity' => $coachingOpportunity ? $this->presentReview($coachingOpportunity) : null,
            'call_intelligence_url' => route('operations.owner.call-intelligence', [
                'from' => Carbon::parse($from)->toDateString(),
                'to' => Carbon::parse($to)->toDateString(),
            ]),
            'arkademy_advisor_calls_url' => EcosystemArkademyBridge::advisorIncomingCallsUrl(),
            'review_count' => $reviews->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentReview(CommunicationReview $review): array
    {
        if ($review->callSession !== null) {
            $row = $this->intelligenceQuery->presentCallRow($review->callSession);
            $channelLabel = 'call';
        } elseif ($review->smsSlice !== null) {
            $row = $this->smsIntelligenceQuery->presentRow($review->smsSlice);
            $channelLabel = 'sms';
        } else {
            return [];
        }

        return [
            'customer_name' => $row['customer_name'] ?? ($channelLabel === 'sms' ? 'Unknown texter' : 'Unknown caller'),
            'advisor_name' => $row['staff_name'] ?? $review->advisor?->name ?? 'Unassigned',
            'summary' => $row['summary'],
            'strengths' => $review->strengths ?? [],
            'opportunities' => $review->opportunities ?? [],
            'why_it_worked' => $this->headlineFromStrengths($review, $row, $channelLabel),
            'what_to_improve' => $this->headlineFromOpportunities($review, $row),
            'transcript_url' => $row['show_url'],
            'composite_score' => $review->composite_score,
            'coaching_opportunity_weight' => $review->coaching_opportunity_weight,
            'channel_label' => $channelLabel,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function headlineFromStrengths(CommunicationReview $review, array $row, string $channelLabel = 'call'): string
    {
        $strength = collect($review->strengths ?? [])->first();

        if (is_string($strength) && trim($strength) !== '') {
            return trim($strength);
        }

        $notes = is_string($row['coaching_notes'] ?? null) ? trim((string) $row['coaching_notes']) : '';

        return $notes !== '' ? $notes : ($channelLabel === 'sms'
            ? 'Strong advisor presence in this SMS thread.'
            : 'Strong advisor presence on this call.');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function headlineFromOpportunities(CommunicationReview $review, array $row): string
    {
        $opportunity = collect($review->opportunities ?? [])->first();

        if (is_string($opportunity) && trim($opportunity) !== '') {
            return trim($opportunity);
        }

        if (is_string($row['missed_upsell_notes'] ?? null) && trim((string) $row['missed_upsell_notes']) !== '') {
            return trim((string) $row['missed_upsell_notes']);
        }

        $notes = is_string($row['coaching_notes'] ?? null) ? trim((string) $row['coaching_notes']) : '';

        return $notes !== '' ? $notes : 'Review transcript for a specific coaching angle.';
    }
}
