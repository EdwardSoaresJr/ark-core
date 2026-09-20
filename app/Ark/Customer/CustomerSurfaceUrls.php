<?php

namespace App\Ark\Customer;

use App\Ark\Runtime\Surfaces\SurfaceRouting;
use Illuminate\Support\Facades\Route;

final class CustomerSurfaceUrls
{
    public static function publicHome(): string
    {
        return self::portalAccess();
    }

    public static function portalAccess(): string
    {
        if (SurfaceRouting::enabled()) {
            return SurfaceRouting::urlForHost(SurfaceRouting::customerHost(), '/portal/access');
        }

        return Route::has('portal.access') ? route('portal.access') : url('/portal/access');
    }

    public static function portalHome(): string
    {
        if (SurfaceRouting::enabled()) {
            return SurfaceRouting::urlForHost(SurfaceRouting::customerHost(), '/portal/home');
        }

        return Route::has('portal.home') ? route('portal.home') : url('/portal/home');
    }
}
