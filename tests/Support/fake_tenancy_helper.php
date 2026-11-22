<?php

/**
 * Global tenancy() helper used only when Stancl is not installed (Core test suite).
 */
if (! function_exists('tenancy')) {
    function tenancy(): object
    {
        return \Tests\Support\FakeStanclTenancy::$instance
            ?? new class
            {
                public bool $initialized = false;

                public mixed $tenant = null;
            };
    }
}
