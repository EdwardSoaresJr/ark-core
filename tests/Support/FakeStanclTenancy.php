<?php

namespace Tests\Support;

/**
 * Minimal Stancl tenancy stand-in for Core tests that must not depend on stancl/tenancy.
 * Defines a global tenancy() helper once; keep {@see self::$instance} cleared between tests.
 */
final class FakeStanclTenancy
{
    public static ?self $instance = null;

    public bool $initialized = false;

    public mixed $tenant = null;

    public static function enable(mixed $tenant = null): void
    {
        self::ensureHelper();
        self::$instance = new self;
        self::$instance->initialized = true;
        self::$instance->tenant = $tenant ?? (object) ['id' => 'stancl-test-shop'];
    }

    public static function clear(): void
    {
        if (self::$instance === null) {
            return;
        }

        self::$instance->initialized = false;
        self::$instance->tenant = null;
        self::$instance = null;
    }

    private static function ensureHelper(): void
    {
        if (function_exists('tenancy')) {
            return;
        }

        require_once __DIR__.'/fake_tenancy_helper.php';
    }
}
