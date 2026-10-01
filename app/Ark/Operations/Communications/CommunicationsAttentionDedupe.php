<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\PhoneNumber;

/**
 * Collapse Needs Attention rows that represent the same customer or phone.
 */
final class CommunicationsAttentionDedupe
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $customerByPhone
     */
    public function dedupeKey(array $row, array $customerByPhone = []): string
    {
        if (isset($row['customer_id']) && (int) $row['customer_id'] > 0) {
            return 'customer:'.(int) $row['customer_id'];
        }

        $normalized = PhoneNumber::normalize((string) (
            $row['normalized_phone']
            ?? $row['normalized_from']
            ?? $row['display_phone']
            ?? ''
        ));

        if ($normalized !== null && isset($customerByPhone[$normalized])) {
            return 'customer:'.$customerByPhone[$normalized];
        }

        if ($normalized !== null) {
            return 'phone:'.$normalized;
        }

        return match ((string) ($row['kind'] ?? '')) {
            'call' => 'call:'.(int) ($row['call_session_id'] ?? 0),
            default => 'conversation:'.(int) ($row['conversation_id'] ?? 0),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function dedupe(array $rows): array
    {
        $customerByPhone = [];

        foreach ($rows as $row) {
            $phone = PhoneNumber::normalize((string) (
                $row['normalized_phone']
                ?? $row['normalized_from']
                ?? $row['display_phone']
                ?? ''
            ));

            if ($phone !== null && isset($row['customer_id']) && (int) $row['customer_id'] > 0) {
                $customerByPhone[$phone] = (int) $row['customer_id'];
            }
        }

        // Shop-turn posture rows are often synthesized from the call itself.
        // The call row is the richer projection of that same pressure, so it
        // wins the collapse; genuine unread-message rows keep their precedence.
        $keysWithCallRow = [];

        foreach ($rows as $row) {
            if (($row['kind'] ?? '') === 'call') {
                $keysWithCallRow[$this->dedupeKey($row, $customerByPhone)] = true;
            }
        }

        $seen = [];
        $deduped = [];

        foreach ($rows as $row) {
            $key = $this->dedupeKey($row, $customerByPhone);

            if (isset($seen[$key])) {
                continue;
            }

            if (($row['state'] ?? '') === 'shop_turn' && isset($keysWithCallRow[$key])) {
                continue;
            }

            $seen[$key] = true;
            $deduped[] = $row;
        }

        return $deduped;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function count(array $rows): int
    {
        return count($this->dedupe($rows));
    }
}
