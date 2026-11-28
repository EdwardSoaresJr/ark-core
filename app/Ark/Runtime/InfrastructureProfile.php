<?php

namespace App\Ark\Runtime;

use App\Ark\Operations\Documents\PdfRuntimePaths;

/**
 * Describes what infrastructure this process can use.
 *
 * Profiles are availability signals for Docker / VPS / shared hosting — not
 * commercial entitlements and not artificial product feature flags.
 */
final class InfrastructureProfile
{
    public const FULL = 'full';

    public const STANDARD = 'standard';

    public const CONSTRAINED = 'constrained';

    /**
     * @return array{
     *     profile: self::FULL|self::STANDARD|self::CONSTRAINED,
     *     redis: bool,
     *     chromium_pdf: bool,
     *     native_pdf: bool,
     *     detached_finalize: bool,
     *     long_running_workers: bool,
     *     websockets: bool,
     *     notes: list<string>
     * }
     */
    public static function inspect(): array
    {
        $redis = self::redisExtensionPresent();
        $chromium = self::chromiumPdfAvailable();
        $nativePdf = self::nativePdfAvailable();
        $detached = self::detachedFinalizeAvailable();
        $workers = self::longRunningWorkersConfigured();
        $websockets = self::websocketsConfigured();

        $notes = [];
        if (! $redis) {
            $notes[] = 'Redis extension unavailable — use file/database cache and session; prefer sync or database queue with cron.';
        }
        if (! $chromium && $nativePdf) {
            $notes[] = 'Chromium PDF unavailable — native PHP PDF engine will render shop documents.';
        }
        if (! $chromium && ! $nativePdf) {
            $notes[] = 'No PDF engine available — install Chromium/Node or the native PHP PDF package.';
        }
        if (! $detached) {
            $notes[] = 'Detached CLI finalize unavailable — installer runs finalize inside the HTTP request.';
        }
        if (! $workers) {
            $notes[] = 'Horizon/Redis workers not configured — use sync queue or cron-bounded queue:work.';
        }
        if (! $websockets) {
            $notes[] = 'Reverb/WebSockets not configured — realtime UI falls back to polling/log broadcast.';
        }

        $profile = self::CONSTRAINED;
        if ($redis && $chromium && $workers && $websockets && $detached) {
            $profile = self::FULL;
        } elseif (($redis || $detached) && ($chromium || $nativePdf)) {
            $profile = self::STANDARD;
        }

        return [
            'profile' => $profile,
            'redis' => $redis,
            'chromium_pdf' => $chromium,
            'native_pdf' => $nativePdf,
            'detached_finalize' => $detached,
            'long_running_workers' => $workers,
            'websockets' => $websockets,
            'notes' => $notes,
        ];
    }

    public static function redisExtensionPresent(): bool
    {
        return extension_loaded('redis') || class_exists(\Predis\Client::class);
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
