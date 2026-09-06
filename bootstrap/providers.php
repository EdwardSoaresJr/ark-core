<?php

use App\Providers\AppServiceProvider;
use App\Providers\BookingSurfaceServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\ShopMemoryServiceProvider;
use App\Ark\Dragon\Agent\DragonAgentServiceProvider;

return [
    AppServiceProvider::class,
    BookingSurfaceServiceProvider::class,
    HorizonServiceProvider::class,
    ShopMemoryServiceProvider::class,
    DragonAgentServiceProvider::class,
];
