<?php

namespace App\Ark\Runtime\Exceptions;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class ExceptionReportCopyController
{
    public function __invoke(
        string $reportId,
        Request $request,
        ExceptionReportArchive $archive,
        ExceptionReportMarkdown $markdown,
    ): View {
        $payload = $this->resolvePayload($reportId, $archive);

        abort_if($payload === null, 404);

        $body = is_string($payload['report_markdown'] ?? null) && $payload['report_markdown'] !== ''
            ? (string) $payload['report_markdown']
            : $markdown->format($payload);

        return view('runtime.exception-report-copy', [
            'reportId' => $reportId,
            'markdown' => $body,
            'reportFilename' => $payload['report_filename'] ?? $payload['filename'] ?? null,
            'exceptionClass' => (string) ($payload['exception_class'] ?? 'Exception'),
            'exceptionMessage' => (string) ($payload['exception_message'] ?? ''),
            'vpsCommand' => 'php artisan errors:recent --id='.$reportId,
            'showCommand' => filled($payload['report_filename'] ?? $payload['filename'] ?? null)
                ? 'php artisan errors:recent --show='.($payload['report_filename'] ?? $payload['filename'])
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePayload(string $reportId, ExceptionReportArchive $archive): ?array
    {
        $cached = Cache::get('exception-report:'.$reportId);

        if (is_array($cached)) {
            return $cached;
        }

        $archived = $archive->findById($reportId);

        if ($archived === null) {
            return null;
        }

        return array_merge($archived, [
            'report_id' => $reportId,
            'report_filename' => $archived['filename'] ?? null,
        ]);
    }
}
