<?php

namespace App\Ark\Growth\Redirects;

use App\Ark\Growth\Models\GrowthRedirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class RedirectResolver
{
    private const CACHE_KEY = 'growth.redirects.active';

    public function resolve(Request $request): ?GrowthRedirect
    {
        if (! config('growth.redirects_enabled', true)) {
            return null;
        }

        $path = '/'.trim($request->path(), '/');
        if ($path === '//') {
            $path = '/';
        }

        $redirects = $this->activeRedirects();

        if (isset($redirects[$path])) {
            return $redirects[$path];
        }

        foreach ($redirects as $from => $redirect) {
            if (! $redirect->is_wildcard) {
                continue;
            }

            $pattern = str_replace('*', '.*', preg_quote(rtrim($from, '*'), '/'));
            if (preg_match('/^'.$pattern.'$/', $path) === 1) {
                return $redirect;
            }
        }

        return null;
    }

    public function wouldLoop(GrowthRedirect $redirect, string $targetPath): bool
    {
        if ($redirect->isGone()) {
            return false;
        }

        $targetPath = '/'.trim($targetPath, '/');

        if ($targetPath === $redirect->from_path) {
            return true;
        }

        $chain = [$redirect->from_path];
        $next = $targetPath;
        $guard = 0;

        while ($guard++ < 10) {
            $nextRedirect = GrowthRedirect::query()
                ->where('is_active', true)
                ->where('from_path', $next)
                ->first();

            if ($nextRedirect === null || $nextRedirect->isGone()) {
                return false;
            }

            if (in_array($nextRedirect->from_path, $chain, true)) {
                return true;
            }

            $chain[] = $nextRedirect->from_path;
            $next = '/'.trim((string) $nextRedirect->to_path, '/');
        }

        return true;
    }

    public function recordHit(GrowthRedirect $redirect): void
    {
        GrowthRedirect::query()
            ->whereKey($redirect->id)
            ->update([
                'hit_count' => $redirect->hit_count + 1,
                'last_hit_at' => now(),
            ]);

        Cache::forget(self::CACHE_KEY);
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, GrowthRedirect>
     */
    private function activeRedirects(): array
    {
        /** @var array<string, GrowthRedirect> $redirects */
        $redirects = Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function (): array {
            return GrowthRedirect::query()
                ->where('is_active', true)
                ->get()
                ->keyBy('from_path')
                ->all();
        });

        return $redirects;
    }
}
