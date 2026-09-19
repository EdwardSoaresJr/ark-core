<?php

namespace App\Ark\Operations\Financial;

use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<RepairOrderLedgerEntry>
 */
final class RepairOrderLedgerEntryQuery extends Builder
{
    public function get($columns = ['*'])
    {
        return parent::get($this->aliasColumns($columns));
    }

    public function select($columns = ['*'])
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        return parent::select($this->aliasColumns($columns));
    }

    public function addSelect($column)
    {
        $columns = is_array($column) ? $column : func_get_args();

        return parent::addSelect($this->aliasColumns($columns));
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_string($column)) {
            $column = $this->aliasColumn($column);
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    public function orderBy($column, $direction = 'asc')
    {
        if (is_string($column)) {
            $column = $this->aliasColumn($column);
        }

        return parent::orderBy($column, $direction);
    }

    /**
     * @param  array<int, mixed>|string  $columns
     * @return array<int, mixed>|string
     */
    private function aliasColumns(array|string $columns): array|string
    {
        if (is_string($columns)) {
            return $this->aliasColumn($columns);
        }

        return array_map(
            fn (mixed $column): mixed => is_string($column) ? $this->aliasColumn($column) : $column,
            $columns,
        );
    }

    private function aliasColumn(string $column): string
    {
        $separator = strrpos($column, '.');
        $bare = $separator === false ? $column : substr($column, $separator + 1);

        if ($bare !== 'method') {
            return $column;
        }

        return $separator === false
            ? 'payment_method'
            : substr($column, 0, $separator + 1).'payment_method';
    }
}
