<?php

namespace App\Ark\Import;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DatabaseLegacyArkSmsReader implements LegacyArkSmsReader
{
    private Connection $connection;

    public function __construct()
    {
        $this->connection = DB::connection(config('legacy-arksms-import.connection'));
    }

    public function tableNames(): array
    {
        $rows = $this->connection->select('SHOW TABLES');
        $key = 'Tables_in_'.$this->connection->getDatabaseName();

        return array_map(static fn (object $row): string => (string) $row->{$key}, $rows);
    }

    public function columnNames(string $table): array
    {
        return Schema::connection(config('legacy-arksms-import.connection'))->getColumnListing($table);
    }

    public function customers(?int $afterLegacyId = null, ?int $legacyCustomerId = null, ?int $limit = null): \Traversable
    {
        if (LegacyArkSmsImportConfig::customerScope() === 'vehicle_or_ro_customers') {
            yield from $this->queryCustomerUsers($afterLegacyId, $legacyCustomerId, $limit);

            return;
        }

        yield from $this->queryTable('customers', $afterLegacyId, $legacyCustomerId, $limit);
    }

    public function vehicles(?int $afterLegacyId = null, ?int $legacyCustomerId = null, ?int $limit = null): \Traversable
    {
        yield from $this->queryTable('vehicles', $afterLegacyId, $legacyCustomerId, $limit, 'customer_id');
    }

    public function repairOrders(?int $afterLegacyId = null, ?int $legacyCustomerId = null, ?int $limit = null): \Traversable
    {
        if ($this->usesRepairOrderStatusJoin()) {
            yield from $this->queryRepairOrdersWithStatus($afterLegacyId, $legacyCustomerId, $limit);

            return;
        }

        yield from $this->queryTable('repair_orders', $afterLegacyId, $legacyCustomerId, $limit, 'customer_id');
    }

    public function concernsForRepairOrder(int $legacyRepairOrderId): \Traversable
    {
        $table = LegacyArkSmsImportConfig::table('concerns');

        if (! $this->tableExists($table)) {
            return;
        }

        $columns = LegacyArkSmsImportConfig::columns('concerns');
        $query = $this->connection->table($table)
            ->where($columns['repair_order_id'], $legacyRepairOrderId)
            ->orderBy($columns['id']);

        if (isset($columns['deleted_at'])) {
            $query->whereNull($columns['deleted_at']);
        }

        foreach ($query->cursor() as $row) {
            yield $this->mapRow((array) $row, $columns);
        }
    }

    public function linesForRepairOrder(int $legacyRepairOrderId): \Traversable
    {
        $table = LegacyArkSmsImportConfig::table('lines');

        if (! $this->tableExists($table)) {
            return;
        }

        $columns = LegacyArkSmsImportConfig::columns('lines');
        $query = $this->connection->table($table)
            ->where($columns['repair_order_id'], $legacyRepairOrderId)
            ->orderBy($columns['id']);

        if (isset($columns['deleted_at'])) {
            $query->whereNull($columns['deleted_at']);
        }

        foreach ($query->cursor() as $row) {
            $mapped = $this->mapRow((array) $row, $columns);
            $mapped = $this->normalizeLineRow($mapped, (array) $row);

            yield $mapped;
        }
    }

    public function invoicesForRepairOrder(int $legacyRepairOrderId): \Traversable
    {
        $table = LegacyArkSmsImportConfig::table('invoices');

        if (! $this->tableExists($table)) {
            return;
        }

        $columns = LegacyArkSmsImportConfig::columns('invoices');
        $query = $this->connection->table($table)
            ->where($columns['repair_order_id'], $legacyRepairOrderId)
            ->orderBy($columns['id']);

        foreach ($query->cursor() as $row) {
            yield $this->mapRow((array) $row, $columns);
        }
    }

    /**
     * @return \Traversable<int, array<string, mixed>>
     */
    private function queryCustomerUsers(?int $afterLegacyId, ?int $legacyCustomerId, ?int $limit): \Traversable
    {
        $table = LegacyArkSmsImportConfig::table('customers');
        $columns = LegacyArkSmsImportConfig::columns('customers');
        $vehiclesTable = LegacyArkSmsImportConfig::table('vehicles');
        $repairOrdersTable = LegacyArkSmsImportConfig::table('repair_orders');

        $query = $this->connection->table("{$table} as users")
            ->select('users.*')
            ->where(function ($builder) use ($vehiclesTable, $repairOrdersTable): void {
                $builder->whereExists(function ($sub) use ($vehiclesTable): void {
                    $sub->selectRaw('1')
                        ->from("{$vehiclesTable} as vehicles")
                        ->whereColumn('vehicles.customer_id', 'users.id');
                })->orWhereExists(function ($sub) use ($repairOrdersTable): void {
                    $sub->selectRaw('1')
                        ->from("{$repairOrdersTable} as repair_orders")
                        ->whereColumn('repair_orders.customer_id', 'users.id');
                });
            })
            ->orderBy('users.'.$columns['id']);

        if ($afterLegacyId !== null) {
            $query->where('users.'.$columns['id'], '>', $afterLegacyId);
        }

        if ($legacyCustomerId !== null) {
            $query->where('users.'.$columns['id'], $legacyCustomerId);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        foreach ($query->cursor() as $row) {
            yield $this->mapRow((array) $row, $columns);
        }
    }

    /**
     * @return \Traversable<int, array<string, mixed>>
     */
    private function queryRepairOrdersWithStatus(?int $afterLegacyId, ?int $legacyCustomerId, ?int $limit): \Traversable
    {
        $table = LegacyArkSmsImportConfig::table('repair_orders');
        $columns = LegacyArkSmsImportConfig::columns('repair_orders');
        $statusTable = LegacyArkSmsImportConfig::table('repair_order_statuses');
        $invoicesTable = LegacyArkSmsImportConfig::table('invoices');

        $statusLogsTable = LegacyArkSmsImportConfig::table('repair_order_status_logs');
        $hasStatusLogs = $this->tableExists($statusLogsTable);

        $query = $this->connection->table("{$table} as repair_orders")
            ->leftJoin("{$statusTable} as repair_order_statuses", 'repair_order_statuses.id', '=', 'repair_orders.status_id')
            ->leftJoin("{$invoicesTable} as invoices", function ($join): void {
                $join->on('invoices.repair_order_id', '=', 'repair_orders.id')
                    ->where('invoices.is_active_revision', '=', 1);
            })
            ->select([
                'repair_orders.*',
                'repair_order_statuses.slug as status_slug',
                'invoices.paid_at as invoice_paid_at',
                'invoices.finalized_at as invoice_finalized_at',
            ])
            ->orderBy('repair_orders.'.$columns['id']);

        if ($hasStatusLogs) {
            $completionSlugs = LegacyRepairOrderTimeline::LEGACY_COMPLETION_SLUGS;
            $slugList = implode(',', array_map(
                static fn (string $slug): string => "'".str_replace("'", "''", $slug)."'",
                $completionSlugs,
            ));

            $query->selectSub(function ($sub) use ($statusLogsTable, $statusTable, $slugList): void {
                $sub->from("{$statusLogsTable} as rosl")
                    ->join("{$statusTable} as rosl_status", 'rosl_status.id', '=', 'rosl.to_status_id')
                    ->whereColumn('rosl.repair_order_id', 'repair_orders.id')
                    ->whereRaw("rosl_status.slug in ({$slugList})")
                    ->orderByDesc('rosl.created_at')
                    ->limit(1)
                    ->select('rosl.created_at');
            }, 'legacy_closed_at');
        }

        if ($afterLegacyId !== null) {
            $query->where('repair_orders.'.$columns['id'], '>', $afterLegacyId);
        }

        if ($legacyCustomerId !== null) {
            $query->where('repair_orders.'.$columns['customer_id'], $legacyCustomerId);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        foreach ($query->cursor() as $row) {
            $mapped = $this->mapRow((array) $row, $columns);

            if (empty(trim((string) ($mapped['concern_summary'] ?? '')))) {
                $mapped['concern_summary'] = $row->initial_concern ?? $row->customer_complaint ?? null;
            }

            if (isset($row->legacy_closed_at)) {
                $mapped['legacy_closed_at'] = $row->legacy_closed_at;
            }

            if (isset($row->invoice_finalized_at)) {
                $mapped['invoice_finalized_at'] = $row->invoice_finalized_at;
            }

            yield $this->enrichRepairOrderRow($mapped, (array) $row);
        }
    }

    /**
     * @return \Traversable<int, array<string, mixed>>
     */
    private function queryTable(
        string $configKey,
        ?int $afterLegacyId,
        ?int $legacyCustomerId,
        ?int $limit,
        ?string $customerFilterColumn = null,
    ): \Traversable {
        $table = LegacyArkSmsImportConfig::table($configKey);
        $columns = LegacyArkSmsImportConfig::columns($configKey);

        if (! $this->tableExists($table)) {
            throw new \RuntimeException("Legacy table [{$table}] not found on connection.");
        }

        $query = $this->connection->table($table)->orderBy($columns['id']);

        if ($afterLegacyId !== null) {
            $query->where($columns['id'], '>', $afterLegacyId);
        }

        if ($legacyCustomerId !== null && $customerFilterColumn !== null) {
            $query->where($columns[$customerFilterColumn] ?? $customerFilterColumn, $legacyCustomerId);
        }

        if (isset($columns['deleted_at'])) {
            $query->whereNull($columns['deleted_at']);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        foreach ($query->cursor() as $row) {
            $mapped = $this->mapRow((array) $row, $columns);

            if ($configKey === 'repair_orders') {
                $mapped = $this->enrichRepairOrderRow($mapped, (array) $row);
            }

            yield $mapped;
        }
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function enrichRepairOrderRow(array $mapped, array $row): array
    {
        $internalId = (int) ($mapped['id'] ?? $row['id'] ?? 0);
        $legacyShopNumber = (int) ($row['repair_order_id'] ?? 0);

        if ($legacyShopNumber > 0 && $legacyShopNumber !== $internalId) {
            $mapped['shop_number'] = $legacyShopNumber;
        }

        return array_merge($mapped, (new LegacyArkSmsValueMapper)->repairOrderMileage(array_merge($row, $mapped)));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $columns
     * @return array<string, mixed>
     */
    private function mapRow(array $row, array $columns): array
    {
        $mapped = [];

        foreach ($columns as $canonical => $legacyColumn) {
            if ($legacyColumn === 'computed_subtotal') {
                continue;
            }

            if (array_key_exists($legacyColumn, $row)) {
                $mapped[$canonical] = $row[$legacyColumn];

                continue;
            }

            if (array_key_exists($canonical, $row)) {
                $mapped[$canonical] = $row[$canonical];
            }
        }

        if (! isset($mapped['id']) && isset($row['id'])) {
            $mapped['id'] = $row['id'];
        }

        foreach (['created_at', 'updated_at'] as $timestamp) {
            if (! isset($mapped[$timestamp]) && isset($row[$timestamp])) {
                $mapped[$timestamp] = $row[$timestamp];
            }
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeLineRow(array $mapped, array $row): array
    {
        if (! isset($mapped['subtotal']) || $mapped['subtotal'] === null || $mapped['subtotal'] === '') {
            $quantity = (float) ($mapped['quantity'] ?? 1);
            $unitPrice = (float) ($mapped['unit_price'] ?? 0);
            $mapped['subtotal'] = $quantity * $unitPrice;
        }

        if (! isset($mapped['total']) || $mapped['total'] === null || $mapped['total'] === '') {
            $mapped['total'] = $row['line_total'] ?? $row['total_price'] ?? $mapped['subtotal'];
        }

        if (($mapped['is_overridden'] ?? null) === null && isset($row['price_overridden'])) {
            $mapped['is_overridden'] = (bool) $row['price_overridden'];
        }

        return $mapped;
    }

    private function usesRepairOrderStatusJoin(): bool
    {
        $columns = LegacyArkSmsImportConfig::columns('repair_orders');

        return ($columns['status'] ?? null) === 'status_slug'
            && $this->tableExists(LegacyArkSmsImportConfig::table('repair_order_statuses'));
    }

    private function tableExists(string $table): bool
    {
        return Schema::connection(config('legacy-arksms-import.connection'))->hasTable($table);
    }
}
