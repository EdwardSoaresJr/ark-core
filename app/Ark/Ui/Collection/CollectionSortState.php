<?php

namespace App\Ark\Ui\Collection;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Server-side sort state for Tabler collection List tables.
 */
final class CollectionSortState
{
    /**
     * @param  list<string>  $allowed
     * @param  array<string, 'asc'|'desc'>  $defaultDirs
     * @param  array<string, callable(Builder, string): void>  $columnMap  key => applier(query, dir)
     */
    public function __construct(
        public readonly string $key,
        public readonly string $dir,
        public readonly array $allowed,
        public readonly array $defaultDirs,
        public readonly string $defaultKey,
        private readonly array $columnMap,
    ) {}

    /**
     * @param  list<string>  $allowed
     * @param  array<string, 'asc'|'desc'>  $defaultDirs
     * @param  array<string, callable(Builder, string): void>  $columnMap
     */
    public static function fromRequest(
        Request $request,
        array $allowed,
        string $defaultKey,
        array $defaultDirs,
        array $columnMap,
    ): self {
        $key = (string) $request->query('sort', $defaultKey);
        if (! in_array($key, $allowed, true)) {
            $key = $defaultKey;
        }

        $dir = strtolower((string) $request->query('dir', $defaultDirs[$key] ?? 'asc'));
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = $defaultDirs[$key] ?? 'asc';
        }

        return new self($key, $dir, $allowed, $defaultDirs, $defaultKey, $columnMap);
    }

    public function apply(Builder $query): void
    {
        $applier = $this->columnMap[$this->key] ?? null;
        if ($applier === null) {
            return;
        }

        $applier($query, $this->dir);
    }

    /**
     * @param  array<string, scalar|null>  $baseQuery
     */
    public function url(string $column, string $routeName, array $baseQuery = []): string
    {
        $defaultDir = $this->defaultDirs[$column] ?? 'asc';
        $dir = ($this->key === $column && $this->dir === $defaultDir)
            ? ($defaultDir === 'asc' ? 'desc' : 'asc')
            : $defaultDir;

        $query = array_merge($baseQuery, [
            'sort' => $column,
            'dir' => $dir,
        ]);

        if ($column === $this->defaultKey && $dir === ($this->defaultDirs[$this->defaultKey] ?? 'asc')) {
            unset($query['sort'], $query['dir']);
        }

        return route($routeName, array_filter(
            $query,
            static fn ($value) => $value !== null && $value !== '',
        ));
    }

    public function ariaSort(string $column): ?string
    {
        if ($this->key !== $column) {
            return null;
        }

        return $this->dir === 'asc' ? 'ascending' : 'descending';
    }

    public function isActive(string $column): bool
    {
        return $this->key === $column;
    }
}
