<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\ShopMemoryServiceProvider;
use App\Ark\Growth\GrowthServiceProvider;
use App\Ark\Website\WebsiteServiceProvider;
use App\Ark\Dragon\Agent\DragonAgentServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    GrowthServiceProvider::class,
    WebsiteServiceProvider::class,
    ShopMemoryServiceProvider::class,
    DragonAgentServiceProvider::class,
];
