<?php

namespace App\Ark\Operations\Attention;

use Illuminate\Support\Carbon;

/**
 * Weekly advisor nudge response counts — tune suggestions before automation.
 */
final class AdvisorNudgeWeeklyInsightProjection
{
    /**
     * @return array{
     *     acted: int,
     *     dismissed: int,
     *     action_rate: int|null,
     *     rows: list<array{key: string, label: string, acted: int, dismissed: int, total: int, action_rate: int|null}>
     * }
     */
    public function lastSevenDays(?Carbon $since = null): array
    {
        $since ??= now()->subDays(7);

        $responses = AdvisorNudgeResponse::query()
            ->where('created_at', '>=', $since)
            ->get(['nudge_key', 'response']);

        $byKey = [];

        foreach ($responses as $response) {
            $key = (string) $response->nudge_key;
            $byKey[$key] ??= [
                'key' => $key,
                'label' => $this->labelFor($key),
                'acted' => 0,
                'dismissed' => 0,
                'total' => 0,
            ];

            if ($response->response === AdvisorNudgeResponseKind::Acted) {
                $byKey[$key]['acted']++;
            } else {
                $byKey[$key]['dismissed']++;
            }

            $byKey[$key]['total']++;
        }

        $rows = array_values($byKey);
        usort($rows, fn (array $left, array $right): int => $right['total'] <=> $left['total']);

        $rows = array_map(function (array $row): array {
            $row['action_rate'] = $row['total'] > 0
                ? (int) round(($row['acted'] / $row['total']) * 100)
                : null;

            return $row;
        }, $rows);

        $totalResponses = $responses->count();
        $acted = $responses->where('response', AdvisorNudgeResponseKind::Acted)->count();

        return [
            'acted' => $acted,
            'dismissed' => $responses->where('response', AdvisorNudgeResponseKind::Dismissed)->count(),
            'action_rate' => $totalResponses > 0 ? (int) round(($acted / $totalResponses) * 100) : null,
            'rows' => $rows,
        ];
    }

    private function labelFor(string $nudgeKey): string
    {
        return match ($nudgeKey) {
            'call.mark_handled' => 'Mark call handled',
            'call.log_note' => 'Log call note',
            'call.analysis_follow_up' => 'Call analysis follow-up',
            'conversation.waiting_response' => 'Customer waiting',
            'conversation.multiple_messages' => 'Multiple customer texts',
            'conversation.estimate_views' => 'Estimate viewed again',
            'conversation.estimate_viewed' => 'Estimate viewed',
            'conversation.unassigned' => 'Unassigned thread',
            'conversation.sms_analysis_follow_up' => 'SMS analysis follow-up',
            default => str($nudgeKey)->replace('.', ' · ')->replace('_', ' ')->title()->toString(),
        };
    }
}
