<?php

namespace App\Ark\Runtime\Exceptions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Throwable;

final class ExceptionReportArchive
{
    private const INDEX_FILENAME = '_index.json';

    private const LATEST_FILENAME = 'latest.json';

    private const INDEX_LIMIT = 100;

    /**
     * @param  array<string, mixed>  $context
     * @return array{id: string, filename: string, path: string}|null
     */
    public function store(Throwable $exception, array $context, string $reportId): ?array
    {
        if (! config('errors.report.file.enabled')) {
            return null;
        }

        $directory = $this->directory();

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $reportedAt = now();
        $id = $reportId;

        $filename = sprintf(
            '%s_%s.json',
            $reportedAt->utc()->format('Y-m-d\THis\Z'),
            $id,
        );

        $payload = [
            'id' => $id,
            'filename' => $filename,
            'reported_at' => $reportedAt->toIso8601String(),
            'exception_class' => $context['exception_class'] ?? $exception::class,
            'exception_message' => $context['exception_message'] ?? $exception->getMessage(),
            'status_code' => $context['status_code'] ?? null,
            'environment' => $context['environment'] ?? config('app.env'),
            'url' => $context['url'] ?? null,
            'method' => $context['method'] ?? null,
            'route' => $context['route'] ?? null,
            'user_id' => $context['user_id'] ?? null,
            'user_email' => $context['user_email'] ?? null,
            'ip' => $context['ip'] ?? null,
            'referer' => $context['referer'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'input' => $context['input'] ?? [],
            'trace' => $context['trace'] ?? [],
            'report_markdown' => $context['report_markdown'] ?? null,
        ];

        $path = $directory.DIRECTORY_SEPARATOR.$filename;

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

        $this->writeLatest($payload);
        $this->prependIndex($payload);
        $this->pruneExpired();

        return [
            'id' => $id,
            'filename' => $filename,
            'path' => $path,
        ];
    }

    public function directory(): string
    {
        return (string) config('errors.report.file.path', storage_path('logs/reported-errors'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 10): array
    {
        $indexPath = $this->directory().DIRECTORY_SEPARATOR.self::INDEX_FILENAME;

        if (! File::exists($indexPath)) {
            return [];
        }

        $index = json_decode(File::get($indexPath), true);

        if (! is_array($index)) {
            return [];
        }

        return array_slice($index, 0, max(1, $limit));
    }

    public function read(string $filename): ?array
    {
        $path = $this->directory().DIRECTORY_SEPARATOR.$filename;

        if (! File::exists($path)) {
            return null;
        }

        $payload = json_decode(File::get($path), true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $reportId): ?array
    {
        foreach ($this->recent(self::INDEX_LIMIT) as $row) {
            if (($row['id'] ?? '') === $reportId && filled($row['filename'] ?? null)) {
                return $this->read((string) $row['filename']);
            }
        }

        foreach (File::glob($this->directory().DIRECTORY_SEPARATOR.'*_'.$reportId.'.json') ?: [] as $path) {
            $payload = json_decode(File::get($path), true);

            if (is_array($payload)) {
                return $payload;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeLatest(array $payload): void
    {
        File::put(
            $this->directory().DIRECTORY_SEPARATOR.self::LATEST_FILENAME,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function prependIndex(array $payload): void
    {
        $indexPath = $this->directory().DIRECTORY_SEPARATOR.self::INDEX_FILENAME;
        $existing = [];

        if (File::exists($indexPath)) {
            $decoded = json_decode(File::get($indexPath), true);
            $existing = is_array($decoded) ? $decoded : [];
        }

        array_unshift($existing, [
            'id' => $payload['id'],
            'filename' => $payload['filename'],
            'reported_at' => $payload['reported_at'],
            'exception_class' => $payload['exception_class'],
            'exception_message' => $payload['exception_message'],
            'status_code' => $payload['status_code'],
            'url' => $payload['url'],
            'route' => $payload['route'],
            'user_email' => $payload['user_email'],
        ]);

        File::put(
            $indexPath,
            json_encode(
                array_slice($existing, 0, self::INDEX_LIMIT),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ).PHP_EOL,
        );
    }

    private function pruneExpired(): void
    {
        $days = (int) config('errors.report.file.retention_days', 30);

        if ($days <= 0) {
            return;
        }

        $cutoff = Carbon::now()->subDays($days);
        $directory = $this->directory();

        foreach (File::glob($directory.DIRECTORY_SEPARATOR.'*.json') ?: [] as $path) {
            $basename = basename($path);

            if (in_array($basename, [self::INDEX_FILENAME, self::LATEST_FILENAME], true)) {
                continue;
            }

            if (Carbon::createFromTimestamp(File::lastModified($path))->lessThan($cutoff)) {
                File::delete($path);
            }
        }
    }
}
