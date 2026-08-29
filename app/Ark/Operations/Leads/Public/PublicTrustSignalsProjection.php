<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\Settings\ShopSettings;

/**
 * Packages externally verifiable trust signals once per public render.
 */
final class PublicTrustSignalsProjection
{
    /**
     * @return array{
     *     shop_name: string,
     *     section_title: string,
     *     header_pills: list<array{label: string, href: string|null}>,
     *     hero_chips: list<array{label: string, href: string|null}>,
     *     proof_items: list<array{label: string, detail: string|null, href: string|null}>,
     *     financing: array{
     *         available: bool,
     *         headline: string,
     *         body: string,
     *         learn_more_url: string|null,
     *         wisetack_url: string|null,
     *         synchrony_url: string|null,
     *         synchrony_embed_url: string|null,
     *         synchrony_qr_url: string|null,
     *         synchrony_apply_button_image: string|null
     *     }
     * }
     */
    public function forDisplay(): array
    {
        $surface = PublicSurfaceSettings::current();
        $shop = ShopSettings::current();
        $shopName = $shop->displayName();
        $trust = $surface['trust_signals'];
        $googleRating = (string) $surface['google_rating'];
        $googleReviewsUrl = (string) $surface['google_reviews_url'];
        $hasGoogleReviews = (float) $googleRating > 0 && filled($googleReviewsUrl);
        $financingUrl = route('public.financing');
        $repairPalCertifiedUrl = \Illuminate\Support\Facades\Route::has('public.repairpal.certified')
            ? route('public.repairpal.certified')
            : $trust['repairpal_url'];

        $proofItems = [
            [
                'label' => 'Real diagnostics',
                'detail' => 'We use testing and live data to find the problem before recommending parts.',
                'href' => null,
            ],
        ];

        if ($hasGoogleReviews) {
            $proofItems[] = [
                'label' => $googleRating.' stars on Google',
                'detail' => 'Read what local customers say',
                'href' => $googleReviewsUrl,
            ];
        }

        if ($trust['repairpal_certified']) {
            $proofItems[] = [
                'label' => 'RepairPal Certified',
                'detail' => 'Checked for fair pricing and solid work',
                'href' => $repairPalCertifiedUrl,
            ];
        }

        if ($trust['financing_available']) {
            $proofItems[] = [
                'label' => 'Financing available',
                'detail' => 'Wisetack and Synchrony Car Care™ when the repair qualifies',
                'href' => $financingUrl,
            ];
        }

        $proofItems[] = [
            'label' => '24 month / 24,000 mile shop warranty',
            'detail' => 'On qualifying repairs — parts and labor',
            'href' => \Illuminate\Support\Facades\Route::has('public.warranty')
                ? route('public.warranty')
                : null,
        ];

        $proofItems[] = [
            'label' => 'Family owned',
            'detail' => 'Independent shop in Demo City',
            'href' => null,
        ];

        $headerPills = [];

        if ($hasGoogleReviews) {
            $headerPills[] = [
                'label' => $googleRating.'★ Google',
                'href' => $googleReviewsUrl,
            ];
        }

        if ($trust['repairpal_certified']) {
            $headerPills[] = [
                'label' => 'RepairPal Certified',
                'href' => $repairPalCertifiedUrl,
            ];
        }

        if ($trust['financing_available']) {
            $headerPills[] = [
                'label' => 'Financing',
                'href' => $financingUrl,
            ];
        }

        $warrantyUrl = \Illuminate\Support\Facades\Route::has('public.warranty')
            ? route('public.warranty')
            : null;

        $headerPills[] = [
            'label' => '24/24 shop warranty',
            'href' => $warrantyUrl,
        ];

        $heroChips = [];

        if ($hasGoogleReviews) {
            $heroChips[] = [
                'label' => $googleRating.'★ Google',
                'href' => $googleReviewsUrl,
            ];
        }

        $heroChips[] = [
            'label' => '24/24 shop warranty',
            'href' => $warrantyUrl,
        ];

        if ($trust['repairpal_certified']) {
            $heroChips[] = [
                'label' => 'RepairPal Certified',
                'href' => $repairPalCertifiedUrl,
            ];
        }

        if ($trust['financing_available']) {
            $heroChips[] = [
                'label' => 'Financing',
                'href' => $financingUrl,
            ];
        }

        $heroChips[] = [
            'label' => 'We test first',
            'href' => null,
        ];

        return [
            'shop_name' => $shopName,
            'section_title' => 'What you get at '.$shopName,
            'header_pills' => $headerPills,
            'hero_chips' => $heroChips,
            'proof_items' => $proofItems,
            'financing' => [
                'available' => (bool) $trust['financing_available'],
                'headline' => 'Unexpected repair?',
                'body' => 'Need to spread out the cost? Wisetack and Synchrony Car Care™ can help on repairs that qualify.',
                'learn_more_url' => $financingUrl,
                'wisetack_url' => $trust['wisetack_url'],
                'synchrony_url' => $trust['synchrony_url'],
                'synchrony_embed_url' => $trust['synchrony_embed_url'],
                'synchrony_qr_url' => $trust['synchrony_qr_url'],
                'synchrony_apply_button_image' => SynchronyCarCareUrls::APPLY_BUTTON_IMAGE,
            ],
        ];
    }
}
