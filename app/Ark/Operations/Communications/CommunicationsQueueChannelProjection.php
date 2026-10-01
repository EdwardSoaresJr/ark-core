<?php

namespace App\Ark\Operations\Communications;

final class CommunicationsQueueChannelProjection
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function apply(array $data, CommunicationsSurfaceChannel $active): array
    {
        $attentionRows = array_merge(
            $data['since_last_shift'] ?? [],
            $data['needs_attention_now'] ?? [],
            $data['unknown'] ?? [],
        );

        $data['comms_channel'] = $active;
        $data['comms_channel_tabs'] = $this->tabs($attentionRows, $active);
        $data['since_last_shift'] = $this->filterRows($data['since_last_shift'] ?? [], $active);
        $data['needs_attention_now'] = $this->filterRows($data['needs_attention_now'] ?? [], $active);
        $data['unknown'] = $this->filterRows($data['unknown'] ?? [], $active);
        $data['recent_activity'] = $this->filterRows($data['recent_activity'] ?? [], $active);
        $data['calls_waiting'] = $this->filterRows($data['calls_waiting'] ?? [], $active);
        $data['new_opportunities'] = $this->filterRows($data['new_opportunities'] ?? [], $active);
        $data['needs_shop'] = $this->filterRows($data['needs_shop'] ?? [], $active);
        $data['waiting_customer'] = $this->filterRows($data['waiting_customer'] ?? [], $active);
        $data['recently_resolved'] = $this->filterRows($data['recently_resolved'] ?? [], $active);
        $data['customer_pressure_count'] = count($data['since_last_shift'])
            + count($data['needs_attention_now'])
            + count($data['unknown']);

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $attentionRows
     * @return list<array{slug: string, label: string, count: int, url: string, active: bool}>
     */
    private function tabs(array $attentionRows, CommunicationsSurfaceChannel $active): array
    {
        $uniqueRows = $this->uniqueRows($attentionRows);

        return collect(CommunicationsSurfaceChannel::filterTabs())
            ->map(function (CommunicationsSurfaceChannel $tab) use ($uniqueRows, $active): array {
                $count = $tab === CommunicationsSurfaceChannel::All
                    ? count($uniqueRows)
                    : count(array_filter(
                        $uniqueRows,
                        fn (array $row): bool => $tab->matchesRow($row),
                    ));

                return [
                    'slug' => $tab->value,
                    'label' => $tab->label(),
                    'count' => $count,
                    'url' => $tab === CommunicationsSurfaceChannel::All
                        ? CommunicationsNeedsYou::url()
                        : CommunicationsNeedsYou::url(['channel' => $tab->value]),
                    'active' => $tab === $active,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function filterRows(array $rows, CommunicationsSurfaceChannel $active): array
    {
        if ($active === CommunicationsSurfaceChannel::All) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => $active->matchesRow($row),
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function uniqueRows(array $rows): array
    {
        $seen = [];
        $unique = [];

        foreach ($rows as $row) {
            $key = $this->rowKey($row);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $row;
        }

        return $unique;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowKey(array $row): string
    {
        if (($row['kind'] ?? '') === 'call') {
            return 'call:'.(string) ($row['call_session_id'] ?? $row['occurred_at'] ?? md5(json_encode($row)));
        }

        return 'message:'.(string) ($row['conversation_message_id'] ?? $row['conversation_id'] ?? md5(json_encode($row)));
    }
}
