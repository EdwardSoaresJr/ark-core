<?php

namespace App\Ark\Website;

/**
 * Merges catalog-owned website content onto an existing publication.
 *
 * Publication-owned keys already on the document stay as stored.
 * Catalog-owned keys are replaced from the catalog.
 * Other catalog keys are added only when the publication does not have them.
 */
final class WebsitePublicationCatalogMerge
{
    /**
     * @var list<string>
     */
    public const PUBLICATION_OWNED = [
        'shop_photos',
        'composition_photos',
        'customer_reviews',
        'reviews',
        'customer_quote',
        'customer_quote_attribution',
        'trust_signals',
        'social_profiles',
        'shop_services',
        'common_problem_featured_media',
        'google_reviews_url',
        'google_rating',
        'google_review_count',
        'contact_faqs',
        'contact_visit_notes',
        'response_time_hint',
        'audience_surface_verifications',
        'instrumentation_enabled',
    ];

    /**
     * @var list<string>
     */
    public const CATALOG_OWNED = [
        'common_problems',
        'pages',
        'seo',
        'financing',
        'source',
    ];

    /**
     * @param  array<string, mixed>  $publication
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    public function merge(array $publication, array $catalog): array
    {
        $merged = $publication;

        foreach ($catalog as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (in_array($key, self::PUBLICATION_OWNED, true) && array_key_exists($key, $publication)) {
                continue;
            }

            if (in_array($key, self::CATALOG_OWNED, true) || ! array_key_exists($key, $publication)) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
