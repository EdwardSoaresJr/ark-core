<?php

use App\Ark\Operations\Booking\Http\PublicBookingController;
use App\Ark\Operations\Leads\Measurement\RememberAppointmentAttribution;
use App\Ark\Runtime\Surfaces\SurfaceRouting;
use App\Ark\Website\Http\CoreWebsiteResponse;
use App\Ark\Website\Http\PublicWebsiteController;
use Illuminate\Support\Facades\Route;

// Online booking lives on the customer host whether or not the shop publishes a website here,
// so another site's Book Appointment button can open it (see PublicBookingController).
SurfaceRouting::portalRoutes(function (): void {
    Route::middleware(['web', CoreWebsiteResponse::class])->group(function (): void {
        Route::get('/book', [PublicBookingController::class, 'show'])
            ->middleware(RememberAppointmentAttribution::class)
            ->name('public.book');
        Route::post('/book', [PublicBookingController::class, 'store'])
            ->middleware('throttle:'.PublicBookingController::SUBMIT_LIMITER)
            ->name('public.book.store');
        Route::get('/book/availability', [PublicBookingController::class, 'availability'])
            ->middleware('throttle:60,1')
            ->name('public.book.availability');
        Route::get('/book/popup.js', [PublicBookingController::class, 'popupScript'])
            ->name('public.book.popup');
        Route::get('/book/received/{uuid}', [PublicBookingController::class, 'receipt'])
            ->middleware('signed')
            ->where('uuid', '[a-z0-9-]{8,36}')
            ->name('public.book.received');
    });
});

SurfaceRouting::publicRoutes(function (): void {
    Route::middleware(['web', CoreWebsiteResponse::class, RememberAppointmentAttribution::class])->group(function (): void {
        Route::get('/', [PublicWebsiteController::class, 'home'])->name('public.home');
        Route::get('/about', [PublicWebsiteController::class, 'about'])->name('public.about');
        Route::get('/services', [PublicWebsiteController::class, 'services'])->name('public.services');
        Route::get('/services/{service}', [PublicWebsiteController::class, 'service'])
            ->where('service', 'diagnostics|brakes|maintenance')
            ->name('public.services.show');
        Route::get('/contact', [PublicWebsiteController::class, 'contact'])->name('public.contact');
        Route::get('/common-problems', [PublicWebsiteController::class, 'problems'])->name('public.common-problems.index');
        Route::get('/common-problems/{slug}', [PublicWebsiteController::class, 'problem'])
            ->where('slug', '[a-z0-9-]+')
            ->name('public.common-problems.show');
        Route::get('/financing', [PublicWebsiteController::class, 'financing'])->name('public.financing');
        Route::get('/warranty', [PublicWebsiteController::class, 'page'])->name('public.warranty')->defaults('key', 'warranty');
        Route::get('/repairpal', [PublicWebsiteController::class, 'page'])->name('public.repairpal')->defaults('key', 'repairpal');
        Route::get('/repairpal-certified', [PublicWebsiteController::class, 'page'])->name('public.repairpal.certified')->defaults('key', 'repairpal-certified');
        Route::get('/repairpal-reviews', [PublicWebsiteController::class, 'page'])->name('public.repairpal.reviews')->defaults('key', 'repairpal-reviews');
        Route::get('/repairpal-warranty', [PublicWebsiteController::class, 'page'])->name('public.repairpal.warranty')->defaults('key', 'repairpal-warranty');
        Route::get('/privacy', [PublicWebsiteController::class, 'page'])->name('public.privacy')->defaults('key', 'privacy');
        Route::get('/terms', [PublicWebsiteController::class, 'page'])->name('public.terms')->defaults('key', 'terms');
        Route::get('/robots.txt', [PublicWebsiteController::class, 'robots'])->name('public.robots');
        Route::get('/sitemap.xml', [PublicWebsiteController::class, 'sitemap'])->name('public.sitemap');
        Route::get('/llms.txt', [PublicWebsiteController::class, 'llms'])->name('public.llms');
        Route::get('/leads/thanks', [PublicWebsiteController::class, 'thanks'])->name('public.leads.thanks');
        Route::post('/leads', [PublicWebsiteController::class, 'storeLead'])
            ->middleware('throttle:10,1')
            ->name('public.leads.store');
        // Laravel matches domain routes before domain-less routes. This has
        // to be a fallback or it swallows /up and other explicit Core routes.
        Route::get('/{legacyPath}', [PublicWebsiteController::class, 'legacy'])
            ->where('legacyPath', '.+')
            ->fallback()
            ->name('public.legacy');
    });
});
