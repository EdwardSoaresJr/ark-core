<?php

namespace App\Ark\Operations\Work;

use App\Models\User;
use Illuminate\Support\Carbon;

final class ScheduledDecisionProjection
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     today: list<array<string, mixed>>,
     *     tomorrow: list<array<string, mixed>>,
     *     upcoming: list<array<string, mixed>>,
     *     total_count: int,
     * }
     */
    public function fromRows(array $rows, ?User $viewer = null): array
    {
        $todayStart = now()->startOfDay();
        $tomorrowStart = $todayStart->copy()->addDay();
        $dayAfterTomorrow = $todayStart->copy()->addDays(2);

        $today = [];
        $tomorrow = [];
        $upcoming = [];

        foreach ($rows as $row) {
            $presented = $this->presentRow($row, $viewer?->id);
            $scheduledFor = Carbon::parse($presented['scheduled_for'])->startOfDay();

            if ($scheduledFor->equalTo($todayStart)) {
                $today[] = $presented;

                continue;
            }

            if ($scheduledFor->equalTo($tomorrowStart)) {
                $tomorrow[] = $presented;

                continue;
            }

            if ($scheduledFor->greaterThanOrEqualTo($dayAfterTomorrow)) {
                $upcoming[] = $presented;
            }
        }

        return [
            'today' => $today,
            'tomorrow' => $tomorrow,
            'upcoming' => $upcoming,
            'total_count' => count($today) + count($tomorrow) + count($upcoming),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function presentRow(array $row, ?int $viewerUserId): array
    {
        $schedule = is_array($row['schedule'] ?? null) ? $row['schedule'] : [];
        $scheduleNotes = trim((string) ($schedule['notes'] ?? ''));
        $viewerOwnsSchedule = $viewerUserId !== null
            && isset($schedule['created_by_user_id'])
            && (int) $schedule['created_by_user_id'] === $viewerUserId;

        return [
            'customer_name' => $row['customer_name'] ?? 'Unknown customer',
            'dollars_at_risk_label' => $row['dollars_at_risk_label'] ?? null,
            'notes' => $this->presentNotes($scheduleNotes, (string) ($row['kind'] ?? '')),
            'schedule_label' => $schedule['scheduled_for_label'] ?? '',
            'returns_label' => $this->returnsLabel($row['detail'] ?? ''),
            'scheduled_for' => $schedule['scheduled_for'] ?? now()->toDateString(),
            'is_mine' => $viewerOwnsSchedule,
            'assigned_to_label' => $schedule['assigned_to_label'] ?? 'Shop',
            'context_label' => $this->contextLabel($row),
            'customer_url' => $row['customer_url'] ?? null,
            'repair_order_url' => $row['url'] ?? null,
            'clear_url' => $schedule['clear_url'] ?? null,
            'callback_phone' => $row['callback_phone'] ?? null,
            'text_url' => $row['text_url'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function contextLabel(array $row): string
    {
        $vehicle = trim((string) ($row['vehicle_label'] ?? ''));

        if (filled($row['repair_order_shop_number'] ?? null)) {
            $label = 'RO #'.$row['repair_order_shop_number'];

            if ($vehicle !== '' && $vehicle !== 'Vehicle pending') {
                return $label.' · '.$vehicle;
            }

            return $label;
        }

        return $vehicle !== '' ? $vehicle : 'Repair order';
    }

    private function presentNotes(string $scheduleNotes, string $kind): string
    {
        if ($scheduleNotes !== '') {
            return $this->normalizeScheduleNote($scheduleNotes);
        }

        return $this->defaultNotes($kind);
    }

    private function normalizeScheduleNote(string $notes): string
    {
        $normalized = trim($notes);

        if ($normalized === '') {
            return $normalized;
        }

        if (preg_match('/call\s+and\s+or\s+send\s+sms\s+reminder/i', $normalized) === 1
            || preg_match('/reminder\s+(about|for)\s+apt/i', $normalized) === 1) {
            return 'Appointment reminder';
        }

        if (mb_strlen($normalized) > 72) {
            return mb_substr($normalized, 0, 69).'…';
        }

        return $normalized;
    }

    private function defaultNotes(string $kind): string
    {
        return match ($kind) {
            'estimate_ready_not_sent' => 'Send estimate',
            'approved_work_stalled' => 'Collect payment',
            default => 'Follow up',
        };
    }

    private function returnsLabel(string $detail): string
    {
        if (preg_match('/^Returns\s.+?\sfor reminder/u', $detail, $matches) === 1) {
            return $matches[0];
        }

        return '';
    }
}
