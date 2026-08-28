<?php

use App\Ark\Operations\Leads\Public\PublicCommonProblemShowController;
use App\Ark\Operations\Leads\Public\PublicCommonProblemsIndexController;
use App\Ark\Operations\Leads\Public\PublicContactController;
use App\Ark\Operations\Leads\Public\PublicFinancingController;
use App\Ark\Operations\Leads\Public\PublicBookController;
use App\Ark\Operations\Leads\Public\PublicBookIdentityCompleteController;
use App\Ark\Operations\Leads\Public\PublicBookIdentityEmailCheckController;
use App\Ark\Operations\Leads\Public\PublicBookIdentityEmailSendController;
use App\Ark\Operations\Leads\Public\PublicHomeController;
use App\Ark\Operations\Leads\Public\PublicIndexNowKeyController;
use App\Ark\Operations\Leads\Public\PublicLegacyRedirectController;
use App\Ark\Operations\Leads\Public\PublicLlmsTxtController;
use App\Ark\Operations\Leads\Public\PublicLeadPhoneVerifyCheckController;
use App\Ark\Operations\Leads\Public\PublicLeadPhoneVerifySendController;
use App\Ark\Operations\Leads\Public\PublicLeadStoreController;
use App\Ark\Operations\Leads\Public\PublicLeadThanksController;
use App\Ark\Operations\Leads\Public\PublicPrivacyController;
use App\Ark\Operations\Leads\Public\PublicRepairPalCertifiedController;
use App\Ark\Operations\Leads\Public\PublicRepairPalHubController;
use App\Ark\Operations\Leads\Public\PublicRepairPalReviewsController;
use App\Ark\Operations\Leads\Public\PublicRepairPalWarrantyController;
use App\Ark\Operations\Leads\Public\PublicRobotsController;
use App\Ark\Operations\Leads\Public\PublicSitemapController;
use App\Ark\Operations\Leads\Public\PublicSurfaceEventStoreController;
use App\Ark\Operations\Leads\Public\PublicTermsController;
use App\Ark\Operations\Leads\Public\PublicWarrantyController;
use App\Ark\Growth\Http\Middleware\ApplyGrowthRedirects;
use App\Ark\Growth\Http\Middleware\RecordPublicGrowthPageView;
use App\Ark\Runtime\Surfaces\SurfaceRouting;
use App\Http\Middleware\RedirectPublicLegacyUrls;
use Illuminate\Support\Facades\Route;

SurfaceRouting::publicRoutes(function (): void {
    Route::middleware(['web', RedirectPublicLegacyUrls::class, ApplyGrowthRedirects::class, 'growth.public.pageview'])->group(function (): void {
        Route::get('/robots.txt', PublicRobotsController::class)->name('robots.txt');
        Route::get('/llms.txt', PublicLlmsTxtController::class)->name('llms.txt');
        Route::get('/sitemap.xml', PublicSitemapController::class)->name('sitemap.xml');

        Route::get('/', PublicHomeController::class)->name('public.home');
        Route::get('/book', PublicBookController::class)->name('public.book');

        Route::middleware('throttle:30,1')->group(function (): void {
            Route::post('/surface-events', PublicSurfaceEventStoreController::class)->name('public.surface-events.store');
        });

        Route::middleware('throttle:10,1')->group(function (): void {
            Route::post('/leads/verify/send', PublicLeadPhoneVerifySendController::class)
                ->name('public.leads.verify.send');
            Route::post('/leads/verify/check', PublicLeadPhoneVerifyCheckController::class)
                ->name('public.leads.verify.check');
            Route::post('/book/identity', PublicBookIdentityCompleteController::class)
                ->name('public.book.identity');
            Route::post('/book/identity/email/send', PublicBookIdentityEmailSendController::class)
                ->name('public.book.identity.email.send');
            Route::post('/book/identity/email/check', PublicBookIdentityEmailCheckController::class)
                ->name('public.book.identity.email.check');
            Route::post('/leads', PublicLeadStoreController::class)->name('public.leads.store');
        });

        Route::get('/leads/thanks', PublicLeadThanksController::class)->name('public.leads.thanks');

        Route::get('/contact', PublicContactController::class)->name('public.contact');

        Route::get('/common-problems', PublicCommonProblemsIndexController::class)->name('public.common-problems.index');
        Route::get('/common-problems/{slug}', PublicCommonProblemShowController::class)
            ->where('slug', '[a-z0-9-]+')
            ->name('public.common-problems.show');

        Route::get('/financing', PublicFinancingController::class)->name('public.financing');

        Route::get('/warranty', PublicWarrantyController::class)->name('public.warranty');

        Route::get('/repairpal', PublicRepairPalHubController::class)->name('public.repairpal');
        Route::get('/repairpal-certified', PublicRepairPalCertifiedController::class)->name('public.repairpal.certified');
        Route::get('/repairpal-reviews', PublicRepairPalReviewsController::class)->name('public.repairpal.reviews');
        Route::get('/repairpal-warranty', PublicRepairPalWarrantyController::class)->name('public.repairpal.warranty');

        Route::get('/privacy', PublicPrivacyController::class)->name('public.privacy');
        Route::get('/terms', PublicTermsController::class)->name('public.terms');

        Route::get('/indexnow-{token}.txt', PublicIndexNowKeyController::class)
            ->where('token', '[a-f0-9]{32}')
            ->name('public.indexnow-key');

        Route::get('/{legacyPath}', PublicLegacyRedirectController::class)
            ->where('legacyPath', '^(?!cloud(?:/|$)).+')
            ->name('public.legacy-redirect');

    });
});
