<?php

namespace App\Ark\Operations\Diagnostics;

final class QueryCompositionGetMutation
{
    /**
     * @param  list<array<string, mixed>>  $trace
     * @return array{operation: string, table: string, subsystem: string, sql: string}|null
     */
    public static function classify(string $sql, array $trace): ?array
    {
        if (! preg_match('/^\s*(update|delete|insert)\s+/i', trim($sql), $operationMatch)) {
            return null;
        }

        $operation = strtolower($operationMatch[1]);
        $table = self::extractTable($sql) ?? 'unknown';
        $traceSignature = implode("\n", array_map(
            static fn (array $frame): string => ($frame['class'] ?? '').' '.($frame['file'] ?? ''),
            $trace,
        ));

        $subsystem = match (true) {
            $table === 'call_sessions'
                || str_contains($traceSignature, 'CallSessionQueue') => 'Telephony:CallSessionQueue',
            $table === 'users' && str_contains(strtolower($sql), 'last_seen_at')
                || str_contains($traceSignature, 'StaffCallPresence')
                || str_contains($traceSignature, 'TrackStaffCallPresence') => 'Presence:StaffCallPresence',
            $table === 'repair_order_lines' => 'Financial:RepairOrderLines',
            $table === 'repair_order_worksheet_sessions'
                || str_contains($traceSignature, 'RepairOrderWorksheetSession') => 'Concurrency:WorksheetSession',
            default => 'Unclassified:'.$table,
        };

        return [
            'operation' => $operation,
            'table' => $table,
            'subsystem' => $subsystem,
            'sql' => self::summarizeSql($sql),
        ];
    }

    private static function extractTable(string $sql): ?string
    {
        if (preg_match('/(?:update|into|from)\s+[`"\']?([a-z0-9_]+)[`"\']?/i', $sql, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return null;
    }

    private static function summarizeSql(string $sql): string
    {
        $singleLine = preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);

        return strlen($singleLine) > 140
            ? substr($singleLine, 0, 137).'...'
            : $singleLine;
    }
}
