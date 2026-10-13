<?php

function bootHostedDragon(): bool
{
    $root = dirname(base_path()).'/ark-saas/saas';
    if (! is_file($root.'/src/Dragon/Agent/DragonAgentServiceProvider.php')) {
        return false;
    }

    static $autoload = false;
    if (! $autoload) {
        spl_autoload_register(static function (string $class) use ($root): void {
            $prefix = 'Ark\\Saas\\';
            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $path = $root.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($path)) {
                require_once $path;
            }
        });
        $autoload = true;
    }

    $provider = \Ark\Saas\Dragon\Agent\DragonAgentServiceProvider::class;
    if (app()->getProvider($provider) === null) {
        app()->register($provider);
    }

    return true;
}

function migrateHostedDragon(): void
{
    if (! bootHostedDragon()) {
        return;
    }

    test()->artisan('migrate', [
        '--path' => dirname(base_path()).'/ark-saas/saas/database/migrations/dragon',
        '--realpath' => true,
        '--force' => true,
    ]);
}
