<?php

namespace App\Ark\Customer;

use App\Ark\Runtime\Surfaces\SurfaceRouting;
use Illuminate\Support\Facades\Route;

final class CustomerSurfaceUrls
{
    public static function publicHome(): string
    {
        if (SurfaceRouting::publicEnabled()) {
            return SurfaceRouting::urlForHost((string) SurfaceRouting::publicHost(), '/');
        }

        return Route::has('public.home') ? route('public.home') : url('/');
    }

    public static function commonProblems(): string
    {
        if (SurfaceRouting::publicEnabled()) {
            return SurfaceRouting::urlForHost((string) SurfaceRouting::publicHost(), '/common-problems');
        }

        return Route::has('public.common-problems.index')
            ? route('public.common-problems.index')
            : self::publicHome();
    }

    public static function portalAccess(): string
    {
        if (SurfaceRouting::enabled()) {
            return SurfaceRouting::urlForHost(SurfaceRouting::customerHost(), '/portal/access');
        }

        return route('portal.access');
    }

    public static function portalHome(): string
    {
        if (SurfaceRouting::enabled()) {
            return SurfaceRouting::urlForHost(SurfaceRouting::customerHost(), '/portal/home');
        }

        return route('portal.home');
    }
}
