<?php

namespace App\Ark\Runtime;

use App\Ark\Operations\Documents\PdfRuntimePaths;
use Illuminate\Support\Facades\Redis;

/**
 * Describes what infrastructure this process can use.
 *
 * Profiles are availability signals for Docker / VPS / shared hosting — not
 * commercial entitlements and not artificial product feature flags.
 *
 * Distinguishes configured drivers from services that are actually reachable.
 */
final class InfrastructureProfile
{
    public const FULL = 'full';

    public const STANDARD = 'standard';

    public const CONSTRAINED = 'constrained';

    /**
     * @return array{
     *     profile: self::FULL|self::STANDARD|self::CONSTRAINED,
     *     redis_extension: bool,
     *     redis_reachable: bool,
     *     redis_configured: bool,
     *     chromium_pdf: bool,
     *     native_pdf: bool,
     *     detached_finalize: bool,
     *     long_running_workers_configured: bool,
     *     websockets_configured: bool,
     *     websockets_usable: bool,
     *     notes: list<string>
     * }
     */
    public static function inspect(): array
    {
        $redisExtension = self::redisExtensionPresent();
        $redisConfigured = self::redisConfigured();
        $redisReachable = $redisConfigured && $redisExtension && self::redisReachable();
        $chromium = self::chromiumPdfAvailable();
        $nativePdf = self::nativePdfAvailable();
        $detached = self::detachedFinalizeAvailable();
        $workersConfigured = self::longRunningWorkersConfigured();
        $websocketsConfigured = self::websocketsConfigured();
        $websocketsUsable = $websocketsConfigured && $redisReachable;

        $notes = [];
        if ($redisConfigured && ! $redisReachable) {
            $notes[] = 'Redis is configured but not reachable — falling back guidance: file/database cache and session.';
        }
        if (! $redisExtension) {
            $notes[] = 'Redis extension unavailable — use file/database cache and session; prefer sync or database queue with cron.';
        }
        if (! $chromium && $nativePdf) {
            $notes[] = 'Chromium PDF unavailable — native PHP PDF engine will render shop documents.';
        }
        if (! $chromium && ! $nativePdf) {
            $notes[] = 'No PDF engine available — install Chromium/Node or the native PHP PDF package.';
        }
        if (! $detached) {
            $notes[] = 'Detached CLI finalize unavailable — installer runs finalize inside the HTTP request with resumable stages.';
        }
        if ($workersConfigured && ! $redisReachable) {
            $notes[] = 'Horizon is configured but Redis is unreachable — use ark:queue-work-bounded via cron.';
        }
        if (! $workersConfigured) {
            $notes[] = 'Horizon/Redis workers not configured — use sync queue or cron-bounded ark:queue-work-bounded.';
        }
        if ($websocketsConfigured && ! $websocketsUsable) {
            $notes[] = 'Reverb is configured but not usable without reachable Redis — realtime falls back to polling/log broadcast.';
        }
        if (! $websocketsConfigured) {
            $notes[] = 'Reverb/WebSockets not configured — realtime UI falls back to polling/log broadcast.';
        }

        $profile = self::CONSTRAINED;
        if ($redisReachable && $chromium && $workersConfigured && $websocketsUsable && $detached) {
            $profile = self::FULL;
        } elseif (($redisReachable || $detached || $nativePdf) && ($chromium || $nativePdf)) {
            $profile = self::STANDARD;
        }

        return [
            'profile' => $profile,
            'redis_extension' => $redisExtension,
            'redis_reachable' => $redisReachable,
            'redis_configured' => $redisConfigured,
            'chromium_pdf' => $chromium,
            'native_pdf' => $nativePdf,
            'detached_finalize' => $detached,
            'long_running_workers_configured' => $workersConfigured,
            'websockets_configured' => $websocketsConfigured,
            'websockets_usable' => $websocketsUsable,
            'notes' => $notes,
        ];
    }

    public static function redisExtensionPresent(): bool
    {
        return extension_loaded('redis') || class_exists(\Predis\Client::class);
    }

    public static function redisConfigured(): bool
    {
        $store = (string) config('cache.default', '');
        $queue = (string) config('queue.default', '');
        $session = (string) config('session.driver', '');

        return in_array('redis', [$store, $queue, $session], true)
            || filled(config('database.redis.default.host'));
    }

    public static function redisReachable(): bool
    {
        try {
            Redis::connection()->ping();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function chromiumPdfAvailable(): bool
    {
        try {
            if (! class_exists(\Spatie\Browsershot\Browsershot::class)) {
                return false;
            }

            $discovered = PdfRuntimePaths::discover();

            return filled($discovered['chrome'] ?? null) && filled($discovered['node'] ?? null);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function nativePdfAvailable(): bool
    {
        return class_exists(\Mpdf\Mpdf::class);
    }

    public static function detachedFinalizeAvailable(): bool
    {
        if (! function_exists('exec')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('exec', $disabled, true) || in_array('proc_open', $disabled, true)) {
            return false;
        }

        return true;
    }

    public static function longRunningWorkersConfigured(): bool
    {
        return (string) config('queue.default') === 'redis'
            && class_exists(\Laravel\Horizon\Horizon::class);
    }

    public static function websocketsConfigured(): bool
    {
        $broadcast = (string) config('broadcasting.default', '');

        return $broadcast === 'reverb' && filled(config('broadcasting.connections.reverb.key'));
    }
}
