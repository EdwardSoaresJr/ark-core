<?php

namespace App\Ark\Website;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class WebsiteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')
            ->group(base_path('routes/website.php'));
    }
}
