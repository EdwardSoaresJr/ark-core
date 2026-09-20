<?php

namespace App\Ark\Import;

final class ArrayLegacyArkSmsReader implements LegacyArkSmsReader
{
    /**
     * @param  array{
     *     customers?: list<array<string, mixed>>,
     *     vehicles?: list<array<string, mixed>>,
     *     repair_orders?: list<array<string, mixed>>,
     *     concerns?: list<array<string, mixed>>,
     *     lines?: list<array<string, mixed>>,
     *     invoices?: list<array<string, mixed>>,
     * }  $data
     */
    public function __construct(private array $data) {}

    public function tableNames(): array
    {
        return array_keys($this->data);
    }

    public function columnNames(string $table): array
    {
        $rows = $this->data[$table] ?? [];

        if ($rows === []) {
            return [];
        }

        return array_keys($rows[0]);
    }

    public function customers(?int $afterLegacyId = null, ?int $legacyCustomerId = null, ?int $limit = null): \Traversable
    {
        yield from $this->yieldFiltered('customers', $afterLegacyId, $legacyCustomerId, $limit);
    }

    public function vehicles(?int $afterLegacyId = null, ?int $legacyCustomerId = null, ?int $limit = null): \Traversable
    {
        yield from $this->yieldFiltered('vehicles', $afterLegacyId, $legacyCustomerId, $limit, 'customer_id');
    }

    public function repairOrders(?int $afterLegacyId = null, ?int $legacyCustomerId = null, ?int $limit = null): \Traversable
    {
        yield from $this->yieldFiltered('repair_orders', $afterLegacyId, $legacyCustomerId, $limit, 'customer_id');
    }

    public function concernsForRepairOrder(int $legacyRepairOrderId): \Traversable
    {
        foreach ($this->data['concerns'] ?? [] as $row) {
            if ((int) ($row['repair_order_id'] ?? 0) === $legacyRepairOrderId) {
                yield $row;
            }
        }
    }

    public function linesForRepairOrder(int $legacyRepairOrderId): \Traversable
    {
        foreach ($this->data['lines'] ?? [] as $row) {
            if ((int) ($row['repair_order_id'] ?? 0) === $legacyRepairOrderId) {
                yield $row;
            }
        }
    }

    public function invoicesForRepairOrder(int $legacyRepairOrderId): \Traversable
    {
        foreach ($this->data['invoices'] ?? [] as $row) {
            if ((int) ($row['repair_order_id'] ?? 0) === $legacyRepairOrderId) {
                yield $row;
            }
        }
    }

    /**
     * @return \Traversable<int, array<string, mixed>>
     */
    private function yieldFiltered(
        string $key,
        ?int $afterLegacyId,
        ?int $legacyCustomerId,
        ?int $limit,
        ?string $customerKey = null,
    ): \Traversable {
        $count = 0;

        foreach ($this->data[$key] ?? [] as $row) {
            $id = (int) ($row['id'] ?? 0);

            if ($afterLegacyId !== null && $id <= $afterLegacyId) {
                continue;
            }

            if ($legacyCustomerId !== null && $customerKey !== null && (int) ($row[$customerKey] ?? 0) !== $legacyCustomerId) {
                continue;
            }

            yield $row;

            $count++;

            if ($limit !== null && $count >= $limit) {
                break;
            }
        }
    }
}
