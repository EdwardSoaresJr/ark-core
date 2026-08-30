<?php

namespace App\Ark\Runtime\Exceptions;

use App\Mail\ExceptionOccurredMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ExceptionReporter
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_INPUT_KEY_PATTERNS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'secret',
        'api_key',
        'key',
        'auth',
        'auth_token',
        'access_token',
        'refresh_token',
        'client_secret',
        'signing_secret',
        'webhook_secret',
        'credential',
        'postmark',
    ];

    public function notify(Throwable $exception): void
    {
        if (! config('errors.report.enabled')) {
            return;
        }

        if ($this->isAdHocCommandLineEvaluation($exception)) {
            Log::channel('exceptions')->warning('Suppressed ad-hoc CLI exception from email alerts.', [
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return;
        }

        $context = $this->context($exception);
        $reportId = ExceptionReportIdentity::generate();
        $context['report_id'] = $reportId;
        $context['report_vps_command'] = 'php artisan errors:recent --id='.$reportId;
        $context['report_markdown'] = app(ExceptionReportMarkdown::class)->format($context);

        Log::channel('exceptions')->error($exception->getMessage(), $context);

        $archiveMeta = app(ExceptionReportArchive::class)->store($exception, $context, $reportId);

        if (is_array($archiveMeta)) {
            $context['report_filename'] = $archiveMeta['filename'];
            $context['report_archive_path'] = $archiveMeta['path'];
            $context['report_markdown'] = app(ExceptionReportMarkdown::class)->format($context);
        }

        Cache::put(
            'exception-report:'.$reportId,
            $context,
            now()->addDays(7),
        );

        if (\Illuminate\Support\Facades\Route::has('runtime.exception-reports.copy')) {
            $context['report_copy_url'] = URL::temporarySignedRoute(
                'runtime.exception-reports.copy',
                now()->addDays(7),
                ['reportId' => $reportId],
            );
        }

        $recipient = config('errors.report.email');

        if (! filled($recipient)) {
            return;
        }

        if ($this->wasRecentlyReported($exception, $context)) {
            return;
        }

        try {
            $mail = new ExceptionOccurredMail($context);

            if (config('errors.report.queue')) {
                Mail::to($recipient)->queue($mail);

                return;
            }

            Mail::to($recipient)->send($mail);
        } catch (Throwable $mailException) {
            Log::channel('exceptions')->error('Failed to deliver exception report email.', [
                'mail_error' => $mailException->getMessage(),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function context(Throwable $exception): array
    {
        $request = request();

        return [
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'status_code' => $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : null,
            'environment' => config('app.env'),
            'url' => $request instanceof Request ? $request->fullUrl() : null,
            'method' => $request instanceof Request ? $request->method() : null,
            'route' => $request instanceof Request ? $request->route()?->getName() : null,
            'ip' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? $request->userAgent() : null,
            'user_id' => auth()->id(),
            'user_email' => auth()->user()?->email,
            'referer' => $request instanceof Request ? $request->headers->get('referer') : null,
            'input' => $request instanceof Request ? $this->redactInput($request->all()) : [],
            'trace' => $this->sanitizeTrace($exception->getTrace()),
        ];
    }

    /**
     * Stack args can contain full Eloquent graphs and blow up cache/archive serialization.
     *
     * @param  list<array<string, mixed>>  $trace
     * @return list<array<string, mixed>>
     */
    private function sanitizeTrace(array $trace): array
    {
        return collect($trace)
            ->take(12)
            ->map(function (array $frame): array {
                return array_filter([
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    'function' => $frame['function'] ?? null,
                    'class' => $frame['class'] ?? null,
                    'type' => $frame['type'] ?? null,
                ], fn ($value): bool => $value !== null);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function redactInput(array $input): array
    {
        $redacted = [];

        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $redacted[$key] = $this->redactInput($value);

                continue;
            }

            $redacted[$key] = $this->isSensitiveInputKey((string) $key)
                ? '[redacted]'
                : $value;
        }

        return $redacted;
    }

    protected function isSensitiveInputKey(string $key): bool
    {
        $normalizedKey = strtolower($key);

        foreach (self::SENSITIVE_INPUT_KEY_PATTERNS as $pattern) {
            if (str_contains($normalizedKey, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function wasRecentlyReported(Throwable $exception, array $context): bool
    {
        $seconds = (int) config('errors.report.throttle_seconds', 300);

        if ($seconds <= 0) {
            return false;
        }

        $fingerprint = sha1(implode('|', [
            $exception::class,
            $exception->getMessage(),
            $context['url'] ?? '',
            (string) ($context['status_code'] ?? ''),
        ]));

        return ! Cache::add('exception-report:'.$fingerprint, true, $seconds);
    }

    protected function isAdHocCommandLineEvaluation(Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if (self::traceIndicatesAdHocCli($current->getTrace())) {
                return true;
            }

            // FatalError from uncaught tinker/php -r failures often has an empty
            // getTrace(); the original "Command line code" frame lives only in the message.
            if (str_contains($current->getMessage(), 'Command line code')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $trace
     */
    public static function traceIndicatesAdHocCli(array $trace): bool
    {
        foreach ($trace as $frame) {
            if (($frame['file'] ?? null) === 'Command line code') {
                return true;
            }
        }

        return false;
    }
}
