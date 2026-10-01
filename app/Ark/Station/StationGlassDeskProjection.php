<?php

namespace App\Ark\Station;

/**
 * Advisor desk: owned work plus unclaimed missed calls. Not a Job Board.
 */
final class StationGlassDeskProjection
{
    private const PRESSURE_LIMIT = 5;

    /**
     * @param  array<string, mixed>  $glass
     * @param  array<string, mixed>  $todos
     * @param  array<string, mixed>  $calls
     * @param  array<string, mixed>  $attention
     * @return array<string, mixed>
     */
    public function payload(array $glass, array $todos, array $calls, array $attention): array
    {
        $claimed = [];
        foreach ([...($todos['lanes'] ?? []), ['tasks' => $todos['shared'] ?? []]] as $lane) {
            if (! is_array($lane)) {
                continue;
            }
            foreach ($lane['tasks'] ?? [] as $task) {
                if (! is_array($task)) {
                    continue;
                }
                $callId = $task['call_session_id'] ?? null;
                if (is_int($callId) || is_numeric($callId)) {
                    $claimed[(int) $callId] = true;
                }
            }
        }

        $shared = array_map(
            fn (array $task): array => $this->taskItem($task),
            array_values(array_filter($todos['shared'] ?? [], 'is_array')),
        );

        foreach ($calls['missed'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1 || isset($claimed[$id])) {
                continue;
            }
            $shared[] = $this->missedItem($row);
        }

        $lanes = [];
        foreach ($todos['lanes'] ?? [] as $lane) {
            if (! is_array($lane)) {
                continue;
            }
            $tasks = array_map(
                fn (array $task): array => $this->taskItem($task),
                array_values(array_filter($lane['tasks'] ?? [], 'is_array')),
            );
            $lanes[] = [
                'user_id' => $lane['user_id'] ?? null,
                'name' => $lane['name'] ?? 'Advisor',
                'tasks' => $tasks,
            ];
        }

        return [
            'lanes' => $lanes,
            'shared' => $shared,
            'pressure' => $this->pressure($attention),
        ];
    }

    /**
     * @param  array<string, mixed>  $attention
     * @return array<string, mixed>
     */
    private function pressure(array $attention): array
    {
        $summary = is_array($attention['shop_summary'] ?? null)
            ? $attention['shop_summary']
            : [];
        $rows = array_values(array_filter($attention['rows'] ?? [], 'is_array'));
        $comingIn = array_values(array_filter($attention['coming_in'] ?? [], 'is_array'));
        $oldestParts = 0;

        foreach ($rows as $row) {
            if (in_array('old_waiting_parts', $row['attention_reasons'] ?? [], true)) {
                $oldestParts = max($oldestParts, (int) ($row['age_days'] ?? 0));
            }
        }

        return [
            'waiting_approval_amount_label' => $summary['waiting_approval_amount_label'] ?? '$0',
            'waiting_approval_amount_compact_label' => $this->compactMoney((int) ($summary['waiting_approval_amount_cents'] ?? 0)),
            'waiting_approval_count' => (int) ($summary['waiting_approval'] ?? 0),
            'coming_in_count' => (int) ($summary['coming_in'] ?? 0),
            'next_arrival' => $comingIn[0] ?? null,
            'oldest_waiting_parts_days' => $oldestParts,
            'rows' => array_slice($rows, 0, self::PRESSURE_LIMIT),
            'remaining_count' => max(0, count($rows) - self::PRESSURE_LIMIT),
        ];
    }

    private function compactMoney(int $cents): string
    {
        $dollars = $cents / 100;
        if ($dollars >= 1000) {
            return '$'.rtrim(rtrim(number_format($dollars / 1000, 1), '0'), '.').'k';
        }

        return '$'.number_format($dollars, 0);
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    private function taskItem(array $task): array
    {
        $subtitle = $task['vehicle_label'] ?? null;
        if (($task['call_session_id'] ?? null) !== null && $subtitle === null) {
            $subtitle = 'Return call';
        }

        return [
            'kind' => 'task',
            'id' => $task['id'] ?? null,
            'title' => $task['title'] ?? 'Follow up',
            'subtitle' => $subtitle,
            'assigned_user_id' => $task['assigned_user_id'] ?? null,
            'call_session_id' => $task['call_session_id'] ?? null,
            'repair_order_id' => $task['repair_order_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function missedItem(array $row): array
    {
        $who = $row['customer_label'] ?? $row['from_display'] ?? 'Unknown caller';
        $when = $row['started_label'] ?? null;

        return [
            'kind' => 'missed_call',
            'id' => null,
            'title' => 'Return '.$who,
            'subtitle' => $when !== null ? 'Missed · '.$when : 'Missed',
            'assigned_user_id' => null,
            'call_session_id' => $row['id'] ?? null,
            'repair_order_id' => $row['repair_order_id'] ?? null,
        ];
    }
}
