<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\PhoneNumber;

/**
 * Collapse Communications workspace list rows that represent the same customer or phone.
 *
 * Conversation wins over lead over call — relationship authority, not per-event rows.
 */
final class CommunicationsWorkspaceListDedupe
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function dedupe(array $items): array
    {
        $customerByPhone = $this->buildCustomerByPhone($items);
        $winners = [];

        foreach ($items as $item) {
            $key = $this->identityKey($item, $customerByPhone);

            if (! isset($winners[$key])) {
                $winners[$key] = $item;

                continue;
            }

            $winners[$key] = $this->prefer($winners[$key], $item);
        }

        return array_values($winners);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, int>  $customerByPhone
     */
    public function identityKey(array $item, array $customerByPhone = []): string
    {
        if (isset($item['customer_id']) && (int) $item['customer_id'] > 0) {
            return 'customer:'.(int) $item['customer_id'];
        }

        $normalized = PhoneNumber::normalize((string) ($item['normalized_phone'] ?? ''));

        if ($normalized !== null && isset($customerByPhone[$normalized])) {
            return 'customer:'.$customerByPhone[$normalized];
        }

        if ($normalized !== null) {
            return 'phone:'.$normalized;
        }

        return (string) ($item['key'] ?? spl_object_hash((object) $item));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, int>
     */
    private function buildCustomerByPhone(array $items): array
    {
        $customerByPhone = [];

        foreach ($items as $item) {
            $normalized = PhoneNumber::normalize((string) ($item['normalized_phone'] ?? ''));

            if ($normalized !== null && isset($item['customer_id']) && (int) $item['customer_id'] > 0) {
                $customerByPhone[$normalized] = (int) $item['customer_id'];
            }
        }

        return $customerByPhone;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function prefer(array $current, array $candidate): array
    {
        $currentRank = $this->kindRank((string) ($current['kind'] ?? ''));
        $candidateRank = $this->kindRank((string) ($candidate['kind'] ?? ''));

        if ($candidateRank !== $currentRank) {
            return $candidateRank > $currentRank ? $candidate : $current;
        }

        return strcmp(
            (string) ($candidate['sort_at'] ?? ''),
            (string) ($current['sort_at'] ?? ''),
        ) >= 0 ? $candidate : $current;
    }

    private function kindRank(string $kind): int
    {
        return match ($kind) {
            'conversation' => 3,
            'lead' => 2,
            'call' => 1,
            default => 0,
        };
    }
}
