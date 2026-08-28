<?php

namespace App\Ark\Operations\Leads\Public;

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyBusinessHoursLabel;
use Illuminate\Support\Facades\Storage;

final class PublicSurfaceSettings
{
    public const REPAIRPAL_LISTING_URL = 'https://www.repairpal.com/auto-repair-near-me/auto-repair-in-colorado-springs-colorado/lugs-n-plugs-automotive-auto-repair-in-colorado-springs-co';

    public const SYNCHRONY_CAR_CARE_URL = 'https://www.synchrony.com/financing/car-care/prospecting';

    public const WISETACK_PREQUAL_URL = 'https://wisetack.us/#/uz8sh8e/prequalify';

    /** Homepage composition roles — gallery index assignment, not interchangeable decoration. */
    public const PHOTO_ROLE_HERO = 'hero';

    public const PHOTO_ROLE_DIAGNOSTIC_EVIDENCE = 'diagnostic_evidence';

    public const PHOTO_ROLE_APPOINTMENT_PROCESS = 'appointment_process';

    /** @var list<string> */
    public const PHOTO_COMPOSITION_ROLES = [
        self::PHOTO_ROLE_HERO,
        self::PHOTO_ROLE_DIAGNOSTIC_EVIDENCE,
        self::PHOTO_ROLE_APPOINTMENT_PROCESS,
    ];

    /**
     * Legacy positional fallback when a role has never been explicitly assigned.
     * Preserves production imagery that was selected by array index.
     *
     * @var array<string, int>
     */
    private const PHOTO_ROLE_LEGACY_GALLERY_INDEX = [
        self::PHOTO_ROLE_HERO => 0,
        self::PHOTO_ROLE_DIAGNOSTIC_EVIDENCE => 1,
        self::PHOTO_ROLE_APPOINTMENT_PROCESS => 2,
    ];

    private const LEGACY_HEADLINE = "Not sure what's wrong? Start here.";

    private const RETIRED_HEADLINES = [
        "Not sure what's wrong? Start here.",
        'Need help with your vehicle?',
    ];

    private const LEGACY_RESPONSE_TIME_HINT = 'During business hours: usually within 30–60 minutes.';

    /** @var list<string> */
    private const RETIRED_FABRICATED_QUOTES = [
        'They kept me updated the entire time and found the issue another shop missed.',
        'Honest about what needed fixing — no upsells, just clear options and fair pricing.',
        'Had my brakes done and they walked me through the inspection photos. Felt completely informed.',
        'They tracked down an electrical gremlin two dealers could not figure out. Worth the drive.',
    ];

    /** @var array<string, mixed> */
    public const DEFAULTS = [
        'headline' => 'Accurate Diagnostics. Honest Repairs.',
        'positioning_lede' => 'We find the real problem first. You get a clear estimate before we do any repairs.',
        'google_rating' => '4.9',
        'google_review_count' => 56,
        'google_reviews_url' => 'https://g.page/r/Cf8J_e1XmXpMEAE/review',
        'local_tagline' => 'Family owned in Colorado Springs.',
        'customer_quote' => 'Edward is an amazing mechanic and his shop is meticulously clean and organized. He offered various OE and OEM part selections to help fit my budget.',
        'customer_quote_attribution' => 'Eric',
        'customer_reviews' => [
            [
                'quote' => 'Expecting the worst but Edward and Caleb were great and found it only needed a proper trans service that another shop said they did but left seriously underfilled.',
                'attribution' => 'Richard Conti',
            ],
            [
                'quote' => 'Edward is exceptionally meticulous and methodical in his approach to vehicle repair, while also prioritizing a truly comfortable and transparent customer experience.',
                'attribution' => 'Greg Powell',
            ],
            [
                'quote' => 'Edward is an amazing mechanic and his shop is meticulously clean and organized. He offered various OE and OEM part selections to help fit my budget.',
                'attribution' => 'Eric',
            ],
            [
                'quote' => 'The most trustworthy shop in town — won\'t go anywhere else.',
                'attribution' => 'Bradley Vogleman',
            ],
        ],
        'response_time_hint' => 'During business hours we usually reply within 30–60 minutes.',
        'instrumentation_enabled' => true,
        'trust_signals' => [
            'repairpal_certified' => true,
            'repairpal_url' => self::REPAIRPAL_LISTING_URL,
            'financing_available' => true,
            'wisetack_url' => self::WISETACK_PREQUAL_URL,
            'synchrony_url' => 'https://www.synchrony.com/mmc/CR243778456?sitecode=acewel401',
        ],
        'social_profiles' => [
            'facebook_url' => null,
            'instagram_url' => null,
            'nextdoor_url' => null,
            'youtube_url' => null,
            'arkademy_url' => null,
        ],
        'contact_visit_notes' => null,
        'contact_faqs' => [
            [
                'question' => 'Do I need an appointment?',
                'answer' => 'Appointments help us save bay time for diagnostics. Same-day help is sometimes possible — call or text and we’ll tell you the next open slot.',
            ],
            [
                'question' => 'What is the difference between the shop warranty and RepairPal?',
                'answer' => 'The LugsNPlugs shop warranty is 24 months / 24,000 miles on qualifying parts and labor, where applicable. RepairPal Certified warranty is 12 months / 12,000 miles nationwide on qualifying repairs.',
            ],
            [
                'question' => 'Do you accept customer-supplied parts?',
                'answer' => 'Yes, in most cases. There is an extra labor fee, and the part itself is not covered by our parts warranty. Ask us before buying the part so we can make sure it will work for the repair.',
            ],
            [
                'question' => 'Do you offer towing?',
                'answer' => 'We can help arrange a tow to the shop. Call or text with where you are and what you’re driving.',
            ],
            [
                'question' => 'Do you perform inspections?',
                'answer' => 'Yes. We can inspect the vehicle and tell you what we find. If the cause of a problem isn’t clear, we can diagnose it too.',
            ],
            [
                'question' => 'What forms of payment do you accept?',
                'answer' => 'Major cards, and financing when the repair qualifies. Ask about Wisetack or Synchrony Car Care on the estimate.',
            ],
        ],
        'shop_photos' => [
            [
                'path' => 'shop-photos/shop-bay.webp',
                'alt' => 'Inside the LugsNPlugs service bays',
            ],
            [
                'path' => 'shop-photos/scan-data.webp',
                'alt' => 'Technician reviewing live diagnostic data',
            ],
            [
                'path' => 'shop-photos/lift-inspection.webp',
                'alt' => 'Customer vehicle on the lift for inspection',
            ],
            [
                'path' => 'shop-photos/verifying-findings.webp',
                'alt' => 'Technician verifying findings before advising',
            ],
        ],
        /**
         * Named composition assignments → gallery index (0–3).
         * Missing/null keys fall back to PHOTO_ROLE_LEGACY_GALLERY_INDEX so production
         * imagery does not swap until an operator intentionally reassigns.
         */
        'composition_photos' => [
            self::PHOTO_ROLE_HERO => 0,
            self::PHOTO_ROLE_DIAGNOSTIC_EVIDENCE => 1,
            self::PHOTO_ROLE_APPOINTMENT_PROCESS => 2,
        ],
        // Seeded from CommonProblemRegistry::featuredLocalServices() — presentation only.
        'shop_services' => [
            [
                'title' => 'Auto Repair Colorado Springs',
                'common_problem_slug' => 'auto-repair-colorado-springs',
                'enabled' => true,
            ],
            [
                'title' => 'Mechanic Colorado Springs',
                'common_problem_slug' => 'mechanic-colorado-springs',
                'enabled' => true,
            ],
            [
                'title' => 'Car Diagnostics Colorado Springs',
                'common_problem_slug' => 'car-diagnostics-colorado-springs',
                'enabled' => true,
            ],
            [
                'title' => 'Brake Repair Colorado Springs',
                'common_problem_slug' => 'brake-repair-colorado-springs',
                'enabled' => true,
            ],
            [
                'title' => 'Tune Up Colorado Springs',
                'common_problem_slug' => 'tune-up-colorado-springs',
                'enabled' => true,
            ],
            [
                'title' => 'Audi Repair Colorado Springs',
                'common_problem_slug' => 'audi-repair-colorado-springs',
                'enabled' => true,
            ],
        ],
    ];

    /**
     * @return array{
     *     google_rating: string,
     *     google_review_count: int,
     *     google_reviews_url: string,
     *     local_tagline: string,
     *     instrumentation_enabled: bool,
     *     shop_photos: list<array{path: string, alt: string}>,
     *     composition_photos: array<string, int|null>
     * }
     */
    public static function current(): array
    {
        $stored = self::storedRaw();
        $customerReviews = self::normalizeCustomerReviews($stored);
        $shopPhotos = self::normalizePhotos($stored['shop_photos'] ?? self::DEFAULTS['shop_photos']);

        return [
            'headline' => self::resolvedHeadline($stored['headline'] ?? null),
            'positioning_lede' => self::resolvedPositioningLede($stored['positioning_lede'] ?? null),
            'google_rating' => self::rating($stored['google_rating'] ?? null),
            'google_review_count' => max(0, (int) ($stored['google_review_count'] ?? self::DEFAULTS['google_review_count'])),
            'google_reviews_url' => self::nonEmptyString($stored['google_reviews_url'] ?? null)
                ?? (string) self::DEFAULTS['google_reviews_url'],
            'local_tagline' => self::nonEmptyString($stored['local_tagline'] ?? null)
                ?? (string) self::DEFAULTS['local_tagline'],
            'business_hours_label' => TelephonyBusinessHoursLabel::fromCallFlow(),
            'customer_reviews' => $customerReviews,
            'customer_quote' => $customerReviews[0]['quote'] ?? '',
            'customer_quote_attribution' => $customerReviews[0]['attribution'] ?? '',
            'response_time_hint' => self::resolvedResponseTimeHint($stored['response_time_hint'] ?? null),
            'instrumentation_enabled' => (bool) ($stored['instrumentation_enabled'] ?? self::DEFAULTS['instrumentation_enabled']),
            'trust_signals' => self::normalizeTrustSignals($stored),
            'social_profiles' => ShopSocialProfiles::normalize($stored),
            'contact_visit_notes' => self::nonEmptyString($stored['contact_visit_notes'] ?? null),
            'contact_faqs' => self::normalizeContactFaqs($stored['contact_faqs'] ?? null),
            'shop_photos' => $shopPhotos,
            'composition_photos' => self::normalizeCompositionPhotos(
                $stored['composition_photos'] ?? null,
                array_key_exists('composition_photos', $stored),
            ),
            'shop_services' => self::normalizeShopServices($stored),
        ];
    }

    /**
     * @return list<array{quote: string, attribution: string}>
     */
    public static function reviewsForDisplay(): array
    {
        return self::current()['customer_reviews'];
    }

    /**
     * Enabled shop services for homepage / index grids.
     *
     * @return list<array{title: string, slug: string, href: string}>
     */
    public static function shopServicesForDisplay(): array
    {
        return collect(self::current()['shop_services'])
            ->filter(fn (array $service): bool => ($service['enabled'] ?? false) && ($service['title'] ?? '') !== '')
            ->map(fn (array $service): array => self::serviceForPublic($service))
            ->values()
            ->all();
    }

    /**
     * Live-search catalog: enabled shop services plus transactional common-problem pages.
     *
     * @return list<array{title: string, slug: string, teaser: string, href: string}>
     */
    public static function shopServicesSearchCatalog(): array
    {
        $fromSettings = collect(self::shopServicesForDisplay());
        $seenSlugs = $fromSettings
            ->pluck('slug')
            ->filter(fn (string $slug): bool => $slug !== '' && ! str_starts_with($slug, 'service-'))
            ->flip();

        $fromTransactional = collect(CommonProblemRegistry::localServicePages())
            ->reject(fn (array $problem): bool => isset($seenSlugs[$problem['slug']]))
            ->map(fn (array $problem): array => [
                'title' => (string) $problem['title'],
                'slug' => (string) $problem['slug'],
                'teaser' => (string) ($problem['card_teaser'] ?? ''),
                'href' => route('public.common-problems.show', $problem['slug']),
            ]);

        return $fromSettings
            ->map(fn (array $service): array => [
                'title' => (string) $service['title'],
                'slug' => (string) $service['slug'],
                'teaser' => (string) ($service['card_teaser'] ?? ''),
                'href' => (string) $service['href'],
            ])
            ->concat($fromTransactional)
            ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    public static function instrumentationEnabled(): bool
    {
        return self::current()['instrumentation_enabled'];
    }

    /**
     * @return list<array{url: string, alt: string}>
     */
    public static function photosForDisplay(): array
    {
        return collect(self::current()['shop_photos'])
            ->filter(fn (array $photo): bool => filled($photo['path'] ?? null))
            ->map(fn (array $photo): array => [
                'url' => self::photoUrl((string) $photo['path']),
                'alt' => self::displayAlt((string) ($photo['alt'] ?? '')),
            ])
            ->values()
            ->all();
    }

    /**
     * Resolve a named composition photo from the gallery.
     *
     * @return array{url: string, alt: string, path: string, gallery_index: int}
     */
    public static function photoForComposition(string $role): array
    {
        if (! in_array($role, self::PHOTO_COMPOSITION_ROLES, true)) {
            throw new \InvalidArgumentException("Unknown public composition photo role [{$role}].");
        }

        $current = self::current();
        $gallery = $current['shop_photos'];
        $assigned = $current['composition_photos'][$role] ?? null;
        $index = is_int($assigned)
            ? $assigned
            : (self::PHOTO_ROLE_LEGACY_GALLERY_INDEX[$role] ?? 0);

        $photo = $gallery[$index] ?? ['path' => '', 'alt' => ''];
        $path = (string) ($photo['path'] ?? '');

        if ($path === '') {
            $defaults = self::DEFAULTS['shop_photos'][$index]
                ?? self::DEFAULTS['shop_photos'][self::PHOTO_ROLE_LEGACY_GALLERY_INDEX[$role] ?? 0]
                ?? ['path' => 'shop-photos/shop-bay.webp', 'alt' => ''];
            $path = (string) ($defaults['path'] ?? '');
            $alt = self::displayAlt((string) ($photo['alt'] ?? $defaults['alt'] ?? ''));
        } else {
            $alt = self::displayAlt((string) ($photo['alt'] ?? ''));
        }

        return [
            'url' => self::photoUrl($path),
            'alt' => $alt,
            'path' => $path,
            'gallery_index' => $index,
        ];
    }

    /**
     * @return array<string, array{url: string, alt: string, path: string, gallery_index: int}>
     */
    public static function compositionPhotosForDisplay(): array
    {
        $resolved = [];

        foreach (self::PHOTO_COMPOSITION_ROLES as $role) {
            $resolved[$role] = self::photoForComposition($role);
        }

        return $resolved;
    }

    public static function photoUrl(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'public-surface-photos/') || str_starts_with($path, CommonProblemFeaturedMedia::STORAGE_PREFIX)) {
            return Storage::disk('public')->url($path);
        }

        return asset($path);
    }

    /**
     * Alt must describe the assigned asset — never invent a diagnostic activity.
     */
    private static function displayAlt(string $alt): string
    {
        $trimmed = trim($alt);

        return $trimmed !== '' ? $trimmed : 'Shop photo from Demo Auto Repair';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function persist(array $data): void
    {
        $current = self::current();
        $customerReviews = $current['customer_reviews'];

        $firstQuote = array_key_exists('customer_quote', $data)
            ? self::nonEmptyString($data['customer_quote'])
            : self::nonEmptyString($current['customer_quote']);
        $firstAttribution = array_key_exists('customer_quote_attribution', $data)
            ? (self::nonEmptyString($data['customer_quote_attribution']) ?? '')
            : ($current['customer_quote_attribution'] ?? '');

        if (array_key_exists('customer_quote', $data)) {
            if ($firstQuote !== null && $customerReviews !== []) {
                $customerReviews[0] = [
                    'quote' => $firstQuote,
                    'attribution' => $firstAttribution,
                ];
            } elseif ($firstQuote === null && $customerReviews !== []) {
                array_shift($customerReviews);
            }
        }

        ShopSettings::current()->update([
            'public_surface_settings' => array_merge(ShopPublicSurfaceRaw::read(), [
                'headline' => self::nonEmptyString($data['headline'] ?? null) ?? $current['headline'],
                'positioning_lede' => self::nonEmptyString($data['positioning_lede'] ?? null)
                    ?? $current['positioning_lede'],
                'google_rating' => self::rating($data['google_rating'] ?? $current['google_rating']),
                'google_review_count' => max(0, (int) ($data['google_review_count'] ?? $current['google_review_count'])),
                'google_reviews_url' => self::nonEmptyString($data['google_reviews_url'] ?? null)
                    ?? $current['google_reviews_url'],
                'local_tagline' => self::nonEmptyString($data['local_tagline'] ?? null) ?? '',
                'customer_reviews' => $customerReviews,
                'customer_quote' => $firstQuote ?? '',
                'customer_quote_attribution' => $firstAttribution,
                'response_time_hint' => self::nonEmptyString($data['response_time_hint'] ?? null)
                    ?? $current['response_time_hint'],
                'instrumentation_enabled' => (bool) ($data['instrumentation_enabled'] ?? false),
                'trust_signals' => self::normalizeTrustSignals(
                    array_key_exists('trust_signals', $data)
                        ? $data
                        : ['trust_signals' => $current['trust_signals']],
                ),
                'social_profiles' => ShopSocialProfiles::normalize(
                    array_key_exists('social_profiles', $data)
                        ? ['social_profiles' => $data['social_profiles']]
                        : ['social_profiles' => $current['social_profiles']],
                ),
                'contact_visit_notes' => array_key_exists('contact_visit_notes', $data)
                    ? self::nonEmptyString($data['contact_visit_notes'])
                    : ($current['contact_visit_notes'] ?? null),
                'contact_faqs' => array_key_exists('contact_faqs', $data)
                    ? self::normalizeContactFaqs($data['contact_faqs'])
                    : ($current['contact_faqs'] ?? self::DEFAULTS['contact_faqs']),
                'shop_photos' => self::normalizePhotos($data['shop_photos'] ?? $current['shop_photos']),
                'composition_photos' => array_key_exists('composition_photos', $data)
                    ? self::normalizeCompositionPhotos($data['composition_photos'], true)
                    : self::normalizeCompositionPhotos($current['composition_photos'], true),
                'shop_services' => array_key_exists('shop_services', $data)
                    ? self::normalizeShopServices(['shop_services' => $data['shop_services']])
                    : $current['shop_services'],
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function storedRaw(): array
    {
        $stored = ShopSettings::current()->public_surface_settings;

        return is_array($stored) && $stored !== [] ? $stored : self::DEFAULTS;
    }

    private static function rating(mixed $value): string
    {
        $rating = is_numeric($value) ? (float) $value : (float) self::DEFAULTS['google_rating'];

        return number_format(min(5, max(1, $rating)), 1, '.', '');
    }

    /**
     * Retired FAQ answers that misstated shop policy or used obsolete voice.
     * Matching stored answers are replaced with the current DEFAULTS answer for that question.
     *
     * @var list<string>
     */
    private const RETIRED_CONTACT_FAQ_ANSWERS = [
        'We generally do not install customer-supplied parts. Using parts we source protects warranty coverage and parts quality. Ask an advisor if you have a special situation.',
        'Usually no. Parts we source keep warranty and quality clearer. Ask an advisor if you have a special case.',
        'Yes. We inspect and verify findings before recommending repair — including diagnostics when the concern is unclear.',
        'Yes. We inspect and confirm what’s wrong before we recommend a repair — including diagnostics when the cause isn’t clear.',
    ];

    /**
     * @return list<array{question: string, answer: string}>
     */
    private static function normalizeContactFaqs(mixed $faqs): array
    {
        $defaults = self::DEFAULTS['contact_faqs'];
        $defaultByQuestion = collect($defaults)->keyBy('question');

        if (! is_array($faqs)) {
            return $defaults;
        }

        $normalized = collect($faqs)
            ->take(8)
            ->map(function (mixed $faq) use ($defaultByQuestion): ?array {
                if (! is_array($faq)) {
                    return null;
                }

                $question = self::nonEmptyString($faq['question'] ?? null);
                $answer = self::nonEmptyString($faq['answer'] ?? null);

                if ($question === null || $answer === null) {
                    return null;
                }

                if (in_array($answer, self::RETIRED_CONTACT_FAQ_ANSWERS, true)) {
                    $defaultFaq = $defaultByQuestion->get($question);
                    $defaultAnswer = is_array($defaultFaq)
                        ? self::nonEmptyString($defaultFaq['answer'] ?? null)
                        : null;
                    if ($defaultAnswer !== null) {
                        $answer = $defaultAnswer;
                    }
                }

                return [
                    'question' => $question,
                    'answer' => $answer,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : $defaults;
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private static function resolvedHeadline(?string $stored): string
    {
        $value = self::nonEmptyString($stored);

        if ($value === null || in_array($value, self::RETIRED_HEADLINES, true)) {
            return (string) self::DEFAULTS['headline'];
        }

        return $value;
    }

    private static function resolvedPositioningLede(?string $stored): string
    {
        $value = self::nonEmptyString($stored);
        $retired = [
            'We verify the problem before recommending the repair. Dealer-level diagnostics in Colorado Springs — not code-read-and-guess.',
            'We find the real problem first and provide a clear, detailed estimate before any repairs are done.',
        ];

        if ($value === null || in_array($value, $retired, true)) {
            return (string) self::DEFAULTS['positioning_lede'];
        }

        return $value;
    }

    private static function resolvedResponseTimeHint(?string $stored): string
    {
        $value = self::nonEmptyString($stored);

        if ($value === null || $value === self::LEGACY_RESPONSE_TIME_HINT) {
            return (string) self::DEFAULTS['response_time_hint'];
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return list<array{quote: string, attribution: string}>
     */
    private static function normalizeCustomerReviews(array $stored): array
    {
        if (isset($stored['customer_reviews']) && is_array($stored['customer_reviews'])) {
            $reviews = collect($stored['customer_reviews'])
                ->map(function (mixed $review): array {
                    if (! is_array($review)) {
                        return ['quote' => '', 'attribution' => ''];
                    }

                    return [
                        'quote' => trim((string) ($review['quote'] ?? '')),
                        'attribution' => trim((string) ($review['attribution'] ?? '')),
                    ];
                })
                ->filter(fn (array $review): bool => $review['quote'] !== '')
                ->take(4)
                ->values()
                ->all();

            if ($reviews !== []) {
                if (self::reviewsAreRetiredFabrications($reviews)) {
                    return self::DEFAULTS['customer_reviews'];
                }

                return $reviews;
            }
        }

        $reviews = self::DEFAULTS['customer_reviews'];
        $legacyQuote = self::nonEmptyString($stored['customer_quote'] ?? null);
        $legacyAttribution = self::nonEmptyString($stored['customer_quote_attribution'] ?? null);

        if ($legacyQuote !== null && ! in_array($legacyQuote, self::RETIRED_FABRICATED_QUOTES, true)) {
            $reviews[0] = [
                'quote' => $legacyQuote,
                'attribution' => $legacyAttribution ?? $reviews[0]['attribution'],
            ];
        }

        return $reviews;
    }

    /**
     * @param  list<array{quote: string, attribution: string}>  $reviews
     */
    private static function reviewsAreRetiredFabrications(array $reviews): bool
    {
        $quotes = collect($reviews)
            ->pluck('quote')
            ->filter(fn (string $quote): bool => $quote !== '')
            ->values()
            ->all();

        if ($quotes === []) {
            return false;
        }

        foreach ($quotes as $quote) {
            if (! in_array($quote, self::RETIRED_FABRICATED_QUOTES, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *     repairpal_certified: bool,
     *     repairpal_url: string,
     *     financing_available: bool,
     *     wisetack_url: string|null,
     *     synchrony_url: string,
     *     synchrony_embed_url: string,
     *     synchrony_qr_url: string
     * }
     */
    private static function normalizeTrustSignals(array $stored): array
    {
        $defaults = self::DEFAULTS['trust_signals'];
        $raw = is_array($stored['trust_signals'] ?? null) ? $stored['trust_signals'] : [];

        return [
            'repairpal_certified' => filter_var($raw['repairpal_certified'] ?? $defaults['repairpal_certified'], FILTER_VALIDATE_BOOL),
            'repairpal_url' => self::resolveRepairPalListingUrl(self::nonEmptyString($raw['repairpal_url'] ?? null)),
            'financing_available' => filter_var($raw['financing_available'] ?? $defaults['financing_available'], FILTER_VALIDATE_BOOL),
            'wisetack_url' => self::resolveWisetackUrl(
                self::nonEmptyString($raw['wisetack_url'] ?? null)
                    ?? self::nonEmptyString($defaults['wisetack_url'] ?? null),
            ),
            'synchrony_url' => self::resolveSynchronyUrl(
                self::nonEmptyString($raw['synchrony_url'] ?? null)
                    ?? self::nonEmptyString($defaults['synchrony_url'] ?? null),
            ),
            'synchrony_embed_url' => SynchronyCarCareUrls::embedUrl(),
            'synchrony_qr_url' => SynchronyCarCareUrls::qrUrl(),
        ];
    }

    /**
     * @return list<array{path: string, alt: string}>
     */
    private static function normalizePhotos(mixed $photos): array
    {
        if (! is_array($photos)) {
            return self::DEFAULTS['shop_photos'];
        }

        $normalized = collect($photos)
            ->take(4)
            ->map(function (mixed $photo): array {
                if (! is_array($photo)) {
                    return ['path' => '', 'alt' => ''];
                }

                return [
                    'path' => trim((string) ($photo['path'] ?? '')),
                    'alt' => trim((string) ($photo['alt'] ?? '')),
                ];
            })
            ->values()
            ->all();

        while (count($normalized) < 4) {
            $normalized[] = ['path' => '', 'alt' => ''];
        }

        return $normalized;
    }

    /**
     * @return array<string, int|null>
     */
    private static function normalizeCompositionPhotos(mixed $assignments, bool $explicitlyStored): array
    {
        $defaults = self::DEFAULTS['composition_photos'];
        /** @var array<string, int|null> $resolved */
        $resolved = [];

        foreach (self::PHOTO_COMPOSITION_ROLES as $role) {
            $legacy = self::PHOTO_ROLE_LEGACY_GALLERY_INDEX[$role] ?? 0;

            if (! is_array($assignments) || ! array_key_exists($role, $assignments)) {
                // Never stored → keep legacy index so production imagery does not jump.
                $resolved[$role] = $explicitlyStored ? ($defaults[$role] ?? $legacy) : $legacy;

                continue;
            }

            $value = $assignments[$role];

            if ($value === null || $value === '') {
                $resolved[$role] = $legacy;

                continue;
            }

            if (! is_numeric($value)) {
                $resolved[$role] = $legacy;

                continue;
            }

            $index = (int) $value;
            $resolved[$role] = ($index >= 0 && $index <= 3) ? $index : $legacy;
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return list<array{title: string, common_problem_slug: string|null, enabled: bool}>
     */
    private static function normalizeShopServices(array $stored): array
    {
        if (! isset($stored['shop_services']) || ! is_array($stored['shop_services'])) {
            return self::DEFAULTS['shop_services'];
        }

        $allowedSlugs = array_flip(CommonProblemRegistry::transactionalSlugs());

        $services = collect($stored['shop_services'])
            ->take(24)
            ->map(function (mixed $service) use ($allowedSlugs): ?array {
                if (! is_array($service)) {
                    return null;
                }

                $title = trim((string) ($service['title'] ?? ''));

                if ($title === '') {
                    return null;
                }

                $slug = self::nonEmptyString($service['common_problem_slug'] ?? null);
                if ($slug !== null && ! isset($allowedSlugs[$slug])) {
                    $slug = null;
                }

                $enabled = array_key_exists('enabled', $service)
                    ? filter_var($service['enabled'], FILTER_VALIDATE_BOOL)
                    : true;

                return [
                    'title' => $title,
                    'common_problem_slug' => $slug,
                    'enabled' => $enabled,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return $services !== [] ? $services : self::DEFAULTS['shop_services'];
    }

    /**
     * @param  array{title: string, common_problem_slug?: string|null, enabled?: bool}  $service
     * @return array{title: string, slug: string, href: string, card_teaser: string}
     */
    private static function serviceForPublic(array $service): array
    {
        $title = (string) $service['title'];
        $slug = self::nonEmptyString($service['common_problem_slug'] ?? null);

        if ($slug !== null) {
            $problem = CommonProblemRegistry::find($slug);

            if ($problem !== null) {
                return [
                    'title' => $title,
                    'slug' => $slug,
                    'href' => route('public.common-problems.show', $slug),
                    'card_teaser' => (string) ($problem['card_teaser'] ?? ''),
                ];
            }
        }

        return [
            'title' => $title,
            'slug' => 'service-'.str($title)->slug()->toString(),
            'href' => route('public.common-problems.index').'#local-services-heading',
            'card_teaser' => '',
        ];
    }

    private static function resolveRepairPalListingUrl(?string $url): string
    {
        if ($url === null || trim($url) === '') {
            return self::REPAIRPAL_LISTING_URL;
        }

        $path = rtrim((string) parse_url(trim($url), PHP_URL_PATH), '/');

        if ($path === '' || $path === '/repair-shops' || $path === '/auto-repair-near-me') {
            return self::REPAIRPAL_LISTING_URL;
        }

        return trim($url);
    }

    private static function resolveWisetackUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $trimmed = trim($url);
        $host = strtolower((string) parse_url($trimmed, PHP_URL_HOST));
        $path = rtrim((string) parse_url($trimmed, PHP_URL_PATH), '/');

        if ($host === 'www.wisetack.com' && in_array($path, ['', '/for-customers', '/auto-repair', '/merchants/automotive'], true)) {
            return null;
        }

        return $trimmed;
    }

    private static function resolveSynchronyUrl(?string $url): string
    {
        if ($url === null || trim($url) === '') {
            return SynchronyCarCareUrls::linkUrl();
        }

        $trimmed = trim($url);
        $host = strtolower((string) parse_url($trimmed, PHP_URL_HOST));
        $path = rtrim((string) parse_url($trimmed, PHP_URL_PATH), '/');

        if (in_array($host, ['www.mysynchrony.com', 'mysynchrony.com', 'www.synchrony.com', 'synchrony.com'], true)
            && ($path === '' || str_contains($path, '/merchants/car-care-financing') || str_contains($path, '/financing/car-care/prospecting'))) {
            return SynchronyCarCareUrls::linkUrl();
        }

        if (str_contains($host, 'synchrony.com') && str_contains($path, '/mmc/'.SynchronyCarCareUrls::MERCHANT_ID)) {
            return SynchronyCarCareUrls::linkUrl();
        }

        return $trimmed;
    }
}
