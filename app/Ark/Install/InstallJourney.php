<?php

namespace App\Ark\Install;

/**
 * Which setup route the current deployment may open.
 */
final class InstallJourney
{
    public static function redirectFor(?string $route): ?string
    {
        if ($route === null || InstallationState::isInstalled()) {
            return null;
        }

        if (InstallMode::isManaged()) {
            self::acceptRuntimeDatabase();

            return self::managed($route);
        }

        return self::selfHosted($route);
    }

    public static function databaseReady(): bool
    {
        return (bool) (InstallDraft::all()['db_tested'] ?? false);
    }

    public static function shopReady(): bool
    {
        return self::databaseReady() && filled(InstallDraft::all()['shop_name'] ?? null);
    }

    public static function adminReady(): bool
    {
        $draft = InstallDraft::all();

        return self::shopReady()
            && filled($draft['admin_email'] ?? null)
            && filled(session('install.admin_password'));
    }

    private static function managed(string $route): ?string
    {
        $allowed = [
            'install.shop',
            'install.shop.store',
            'install.progress',
            'install.progress.status',
            'install.complete',
        ];

        if (self::shopReady()) {
            $allowed[] = 'install.admin';
            $allowed[] = 'install.admin.store';
        }

        if (self::adminReady()) {
            $allowed[] = 'install.review';
            $allowed[] = 'install.run';
        }

        if (in_array($route, $allowed, true)) {
            return null;
        }

        return self::customerStep();
    }

    private static function selfHosted(string $route): ?string
    {
        $open = [
            'install.welcome',
            'install.system',
            'install.progress',
            'install.progress.status',
            'install.complete',
        ];

        if (self::systemBlocked()) {
            return in_array($route, $open, true) ? null : 'install.system';
        }

        $open[] = 'install.database';
        $open[] = 'install.database.test';

        if (! self::databaseReady()) {
            return in_array($route, $open, true) ? null : 'install.database';
        }

        $open[] = 'install.shop';
        $open[] = 'install.shop.store';

        if (! self::shopReady()) {
            return in_array($route, $open, true) ? null : 'install.shop';
        }

        $open[] = 'install.admin';
        $open[] = 'install.admin.store';

        if (! self::adminReady()) {
            return in_array($route, $open, true) ? null : 'install.admin';
        }

        return null;
    }

    private static function customerStep(): string
    {
        if (! self::shopReady()) {
            return 'install.shop';
        }

        if (! self::adminReady()) {
            return 'install.admin';
        }

        return 'install.review';
    }

    private static function systemBlocked(): bool
    {
        $checker = app(SystemRequirementsChecker::class);

        return $checker->hasFailures($checker->check());
    }

    private static function acceptRuntimeDatabase(): void
    {
        $runtime = RuntimeDatabaseConfig::read();

        InstallDraft::merge([
            'app_url' => self::applicationUrl(),
            'db_host' => $runtime['host'],
            'db_port' => $runtime['port'],
            'db_database' => $runtime['database'],
            'db_username' => $runtime['username'],
            'db_tested' => true,
            'db_managed' => true,
            'db_manual' => false,
        ]);
        session(['install.db_password' => $runtime['password']]);
    }

    private static function applicationUrl(): string
    {
        $configured = rtrim((string) config('app.url'), '/');
        if (preg_match('#^https?://#i', $configured) === 1) {
            return $configured;
        }

        $request = request();
        $scheme = $request->isSecure() ? 'https' : 'http';

        return $scheme.'://'.$request->getHttpHost();
    }
}
