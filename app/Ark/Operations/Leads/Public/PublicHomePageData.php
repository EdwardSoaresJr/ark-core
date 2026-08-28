<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Growth\Seo\SeoEngine;

/**
 * Shared homepage projection payload for public home and Book overlay underlay.
 */
final class PublicHomePageData
{
    public function __construct(
        private readonly CommonProblemPopularityProjection $popularity,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forView(SeoEngine $seo): array
    {
        $publicSurface = PublicSurfaceSettings::current();
        $customerReviews = PublicSurfaceSettings::reviewsForDisplay();

        return [
            ...PublicSurfacePageData::shared(),
            'seo' => $seo->forHomepage()->toArray(),
            'headline' => $publicSurface['headline'],
            'positioningLede' => $publicSurface['positioning_lede'],
            'localTagline' => $publicSurface['local_tagline'],
            'shopPhotos' => PublicSurfaceSettings::photosForDisplay(),
            'compositionPhotos' => PublicSurfaceSettings::compositionPhotosForDisplay(),
            'featuredCustomerReview' => PublicFeaturedReviewProjection::rotating($customerReviews),
            'featuredCommonProblems' => $this->popularity->sortByPopularity(
                CommonProblemRegistry::featuredForHomepage(),
            ),
            'featuredLocalServices' => $this->popularity->sortByPopularity(
                PublicSurfaceSettings::shopServicesForDisplay(),
            ),
            'searchCatalog' => CommonProblemRegistry::searchCatalog(),
            'localServicesSearchCatalog' => PublicSurfaceSettings::shopServicesSearchCatalog(),
            'customerReviews' => $customerReviews,
        ];
    }
}
