<?php

namespace App\Ark\Operations\LaborGuides\Rte;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;

final class RteCsvImporter
{
    private const DECIMAL_COLUMNS = [
        'add_hr1', 'add_hr2', 'add_hr3', 'add_hr4', 'add_hr5',
        'add_hr6', 'add_hr7', 'add_hr8', 'add_hr9',
        'hi_hr', 'avg_hr', 'lo_hr',
    ];

    /**
     * @return array<string, int>
     */
    public function importDirectory(string $directory, bool $truncate = false, ?string $onlyTable = null, int $chunkSize = 1000): array
    {
        $counts = [];

        foreach (RteImportManifest::TABLES as $table => $definition) {
            if ($onlyTable !== null && $onlyTable !== $table) {
                continue;
            }

            $counts[$table] = $this->importTableWithoutCacheFlush($directory, $table, $truncate, $chunkSize);
        }

        RteLaborGuideAvailability::forgetCachedState();

        return $counts;
    }

    public function importTable(string $directory, string $table, bool $truncate = false, int $chunkSize = 1000): int
    {
        $imported = $this->importTableWithoutCacheFlush($directory, $table, $truncate, $chunkSize);
        RteLaborGuideAvailability::forgetCachedState();

        return $imported;
    }

    private function importTableWithoutCacheFlush(string $directory, string $table, bool $truncate = false, int $chunkSize = 1000): int
    {
        $definition = RteImportManifest::TABLES[$table] ?? null;

        if ($definition === null) {
            throw new \InvalidArgumentException("Unknown RTE table [{$table}].");
        }

        $path = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$definition['file'];

        if (! is_readable($path)) {
            throw new \RuntimeException("CSV not found or unreadable: {$path}");
        }

        if ($truncate) {
            DB::table($table)->truncate();
        }

        if ($this->canUseMysqlLocalInfile()) {
            try {
                return $this->importViaMysqlLocalInfile($path, $table, $definition['columns']);
            } catch (\Throwable) {
                // Fall back to chunked inserts when LOCAL INFILE is disabled server-side.
            }
        }

        return $this->importViaChunkedInsert($path, $table, $definition['columns'], $chunkSize);
    }

    /**
     * @param  list<string>  $columns
     */
    private function importViaMysqlLocalInfile(string $path, string $table, array $columns): int
    {
        $connection = $this->mysqlImportConnection();
        $columnList = implode(', ', array_map(static fn (string $column): string => "`{$column}`", $columns));
        $escapedPath = addslashes(str_replace('\\', '/', $path));

        $before = (int) DB::table($table)->count();

        $connection->statement(<<<SQL
            LOAD DATA LOCAL INFILE '{$escapedPath}'
            INTO TABLE `{$table}`
            FIELDS TERMINATED BY ','
            OPTIONALLY ENCLOSED BY '"'
            LINES TERMINATED BY '\n'
            IGNORE 1 LINES
            ({$columnList})
            SQL);

        return (int) DB::table($table)->count() - $before;
    }

    /**
     * @param  list<string>  $columns
     */
    private function importViaChunkedInsert(string $path, string $table, array $columns, int $chunkSize): int
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open CSV: {$path}");
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            throw new \RuntimeException("CSV header missing: {$path}");
        }

        $imported = 0;
        $batch = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            $batch[] = $this->normalizeRow($columns, $row);

            if (count($batch) >= $chunkSize) {
                DB::table($table)->insert($batch);
                $imported += count($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table($table)->insert($batch);
            $imported += count($batch);
        }

        fclose($handle);

        return $imported;
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string|null>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $columns, array $row): array
    {
        $normalized = [];

        foreach ($columns as $index => $column) {
            $value = $row[$index] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            if ($value === '') {
                $value = null;
            }

            if ($value !== null && in_array($column, self::DECIMAL_COLUMNS, true)) {
                $value = (float) $value;
            }

            $normalized[$column] = $value;
        }

        return $normalized;
    }

    private function canUseMysqlLocalInfile(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    private function mysqlImportConnection(): Connection
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $pdo->setAttribute(PDO::MYSQL_ATTR_LOCAL_INFILE, true);

        return $connection;
    }
}
