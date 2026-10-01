<?php

namespace App\Ark\Import;

interface LegacyArkSmsReader
{
    /**
     * @return list<string>
     */
    public function tableNames(): array;

    /**
     * @return list<string>
     */
    public function columnNames(string $table): array;

    /**
     * @return \Traversable<int, array<string, mixed>>
     */
    public function customers(?int $afterLegacyId = null, ?int $legacyCustomerId = null, ?int $limit = null): \Traversable;

    /**
     * @return \Traversable<int, array<string, mixed>>
     */
    public function vehicles(?int $afterLegacyId = null, ?int $legacyCustomerId = null, ?int $limit = null): \Traversable;

    /**
     * @return \Traversable<int, array<string, mixed>>
     */
    public function repairOrders(?int $afterLegacyId = null, ?int $legacyCustomerId = null, ?int $limit = null): \Traversable;

    /**
     * @return \Traversable<int, array<string, mixed>>
     */
    public function concernsForRepairOrder(int $legacyRepairOrderId): \Traversable;

    /**
     * @return \Traversable<int, array<string, mixed>>
     */
    public function linesForRepairOrder(int $legacyRepairOrderId): \Traversable;

    /**
     * @return \Traversable<int, array<string, mixed>>
     */
    public function invoicesForRepairOrder(int $legacyRepairOrderId): \Traversable;
}
