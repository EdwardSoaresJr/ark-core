<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Growth\Seo\SeoEngine;
use App\Ark\Growth\Seo\ShopSeoContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Public appointment-request surface.
 *
 * Creates website Lead intake (existing authority). Does not write Appointment rows —
 * advisors confirm capacity and schedule in operations.
 *
 * Presentation: modal overlay on the public homepage shell. URL stays /book.
 * When the customer is recognized (portal session), Book Service consumes
 * CustomerRecognitionProjection — vehicles first, schedule last.
 */
class PublicBookController
{
    public function __construct(
        private readonly PublicBookExperienceMode $experienceMode,
        private readonly PublicHomePageData $homePageData,
    ) {}

    public function __invoke(Request $request, SeoEngine $seo): View
    {
        $book = config('public_seo.book', []);
        $context = ShopSeoContext::resolve((string) ($book['description'] ?? ''));
        $experience = $this->experienceMode->resolve($request);
        $home = $this->homePageData->forView($seo);

        return view('public.book', [
            ...$home,
            'seo' => $seo->build(
                title: sprintf('%s — %s', $context['name'], $book['title'] ?? 'Book an Appointment'),
                description: (string) ($book['description'] ?? $context['description']),
                path: '/book',
                schemaContext: $context,
                breadcrumbs: [
                    ['name' => 'Home', 'url' => PublicMarketingUrl::absolute('/')],
                    ['name' => 'Book Appointment', 'url' => PublicMarketingUrl::absolute('/book')],
                ],
                ogImage: $context['image'] ?? null,
            )->toArray(),
            'surfaceContext' => PublicSurfaceContext::book(),
            'requestAvailability' => PublicAppointmentRequest::availabilityProjection(),
            'bookExperience' => $experience,
        ]);
    }
}
