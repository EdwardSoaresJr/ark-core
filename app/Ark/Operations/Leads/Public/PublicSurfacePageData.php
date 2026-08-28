<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Customer\CustomerSurfaceHeaderData;

final class PublicSurfacePageData
{
    /**
     * @return array<string, mixed>
     */
    public static function shared(): array
    {
        $publicSurface = PublicSurfaceSettings::current();
        $phoneVerification = app(LeadPhoneVerification::class);

        return array_merge(CustomerSurfaceHeaderData::viewData(), [
            'responseTimeHint' => $publicSurface['response_time_hint'],
            'googleRating' => $publicSurface['google_rating'],
            'googleReviewCount' => $publicSurface['google_review_count'],
            'googleReviewsUrl' => $publicSurface['google_reviews_url'],
            'customerReviews' => PublicSurfaceSettings::reviewsForDisplay(),
            'trustSignals' => app(PublicTrustSignalsProjection::class)->forDisplay(),
            'phoneVerificationRequired' => $phoneVerification->required(),
            'formRenderedAt' => now()->timestamp,
        ]);
    }
}
