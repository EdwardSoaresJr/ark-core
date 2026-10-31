<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tests\Support\TestEnvironment;

/**
 * Laravel's parallel runner boots an application in the parent process through this trait when it exists. Without it,
 * that process loads the checkout's .env, and the workers it starts inherit those variables.
 */
trait CreatesApplication
{
    public function createApplication(): Application
    {
        TestEnvironment::initialize();
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        TestEnvironment::configureApplication($app);

        if (property_exists($this, 'traitsUsedByTest')) {
            $this->traitsUsedByTest = class_uses_recursive(static::class);
        }

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
