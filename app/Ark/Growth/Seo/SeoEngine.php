<?php

namespace App\Ark\Growth\Seo;

use App\Ark\Growth\Models\GrowthContent;
use App\Ark\Growth\PublicSurface\PublicMarketingUrl;
use App\Ark\Operations\Leads\Public\CommonProblemFeaturedMedia;

final class SeoEngine
{
    public function __construct(
        private readonly SchemaRegistry $schemaRegistry,
    ) {}

    /**
     * @param  list<string>  $schemaTypes
     * @param  list<array{name: string, url: string}>  $breadcrumbs
     * @param  array<string, mixed>  $schemaContext
     */
    public function build(
        string $title,
        string $description,
        string $path,
        bool $indexable = true,
        ?string $robots = null,
        ?string $ogImage = null,
        array $schemaTypes = ['AutoRepair'],
        array $schemaContext = [],
        array $breadcrumbs = [],
    ): SeoPageMeta {
        $canonical = PublicMarketingUrl::absolute($path);
        if ($path !== '/') {
            $canonical = rtrim($canonical, '/');
        }

        $robotsDirective = $robots ?? ($indexable ? 'index, follow' : 'noindex, follow');

        $jsonLd = $this->schemaRegistry->buildMany($schemaTypes, array_merge($schemaContext, [
            'url' => $canonical,
        ]));

        if ($breadcrumbs !== []) {
            $jsonLd[] = $this->schemaRegistry->build('BreadcrumbList', ['items' => $breadcrumbs]);
        }

        $openGraph = [
            'title' => $title,
            'description' => $description,
            'url' => $canonical,
            'image' => $ogImage ?? ($schemaContext['image'] ?? null),
            'type' => 'website',
            'site_name' => $schemaContext['name'] ?? null,
        ];

        $twitter = [
            'card' => filled($openGraph['image']) ? 'summary_large_image' : 'summary',
            'title' => $title,
            'description' => $description,
            'image' => $openGraph['image'],
        ];

        return new SeoPageMeta(
            title: $title,
            description: $description,
            canonical: $canonical,
            robots: $robotsDirective,
            indexable: $indexable,
            openGraph: $openGraph,
            twitter: $twitter,
            jsonLd: $jsonLd,
            breadcrumbs: $breadcrumbs,
        );
    }

    public function forHomepage(): SeoPageMeta
    {
        $home = config('public_seo.home', []);
        $context = ShopSeoContext::resolve((string) ($home['description'] ?? ''));

        $title = filled($home['title'] ?? null)
            ? (string) $home['title']
            : sprintf('%s — %s', $context['name'], (string) ($home['title_suffix'] ?? 'Auto Repair'));

        return $this->build(
            title: $title,
            description: (string) ($home['description'] ?? ''),
            path: '/',
            schemaTypes: ['Organization', 'AutoRepair', 'LocalBusiness'],
            schemaContext: $context,
            ogImage: $context['image'] ?? null,
        );
    }

    public function forThanksPage(): SeoPageMeta
    {
        $thanks = config('public_seo.thanks', []);

        return $this->build(
            title: (string) ($thanks['title'] ?? 'Thanks'),
            description: (string) ($thanks['description'] ?? ''),
            path: '/leads/thanks',
            indexable: false,
            robots: (string) ($thanks['robots'] ?? 'noindex, follow'),
            schemaContext: ShopSeoContext::resolve(),
        );
    }

    public function forCommonProblemsIndex(): SeoPageMeta
    {
        $context = ShopSeoContext::resolve(
            'Symptoms, driving safety, common causes, and when to have your vehicle checked — Colorado Springs independent repair.',
        );

        return $this->build(
            title: sprintf('%s — Common Car Problems', $context['name']),
            description: (string) $context['description'],
            path: '/common-problems',
            schemaTypes: ['AutoRepair'],
            schemaContext: $context,
            breadcrumbs: [
                ['name' => 'Home', 'url' => PublicMarketingUrl::absolute('/')],
                ['name' => 'Common Problems', 'url' => PublicMarketingUrl::absolute('/common-problems')],
            ],
        );
    }

    public function forFinancingPage(): SeoPageMeta
    {
        $financing = config('public_seo.financing', []);
        $context = ShopSeoContext::resolve((string) ($financing['description'] ?? ''));

        return $this->build(
            title: sprintf('%s — %s', $context['name'], $financing['title'] ?? 'Financing'),
            description: (string) ($financing['description'] ?? $context['description']),
            path: '/financing',
            schemaTypes: ['AutoRepair'],
            schemaContext: $context,
            breadcrumbs: [
                ['name' => 'Home', 'url' => PublicMarketingUrl::absolute('/')],
                ['name' => 'Financing', 'url' => PublicMarketingUrl::absolute('/financing')],
            ],
        );
    }

    public function forWarrantyPage(): SeoPageMeta
    {
        $warranty = config('public_seo.warranty', []);
        $context = ShopSeoContext::resolve((string) ($warranty['description'] ?? ''));

        return $this->build(
            title: sprintf('%s — %s', $context['name'], $warranty['title'] ?? 'Repair Warranty'),
            description: (string) ($warranty['description'] ?? $context['description']),
            path: '/warranty',
            schemaTypes: ['AutoRepair'],
            schemaContext: $context,
            breadcrumbs: [
                ['name' => 'Home', 'url' => PublicMarketingUrl::absolute('/')],
                ['name' => 'Warranty', 'url' => PublicMarketingUrl::absolute('/warranty')],
            ],
        );
    }

    /**
     * @param  list<array{question: string, answer: string}>  $faqs
     */
    public function forContactPage(array $faqs = []): SeoPageMeta
    {
        $contact = config('public_seo.contact', []);
        $context = ShopSeoContext::resolve((string) ($contact['description'] ?? ''));

        $schemaTypes = ['Organization', 'AutoRepair', 'LocalBusiness'];

        if ($faqs !== []) {
            $context['faqs'] = $faqs;
            $schemaTypes[] = 'FAQPage';
        }

        return $this->build(
            title: sprintf('%s — %s', $context['name'], $contact['title'] ?? 'Contact'),
            description: (string) ($contact['description'] ?? $context['description']),
            path: '/contact',
            schemaTypes: $schemaTypes,
            schemaContext: $context,
            breadcrumbs: [
                ['name' => 'Home', 'url' => PublicMarketingUrl::absolute('/')],
                ['name' => 'Contact', 'url' => PublicMarketingUrl::absolute('/contact')],
            ],
            ogImage: $context['image'] ?? null,
        );
    }

    public function forRepairPalHubPage(): SeoPageMeta
    {
        $page = config('public_seo.repairpal', []);
        $context = ShopSeoContext::resolve((string) ($page['description'] ?? ''));

        return $this->build(
            title: sprintf('%s — %s', $context['name'], $page['title'] ?? 'RepairPal'),
            description: (string) ($page['description'] ?? $context['description']),
            path: '/repairpal',
            schemaTypes: ['AutoRepair'],
            schemaContext: $context,
            breadcrumbs: [
                ['name' => 'Home', 'url' => PublicMarketingUrl::absolute('/')],
                ['name' => 'RepairPal', 'url' => PublicMarketingUrl::absolute('/repairpal')],
            ],
        );
    }

    public function forRepairPalCertifiedPage(): SeoPageMeta
    {
        $page = config('public_seo.repairpal_certified', []);
        $context = ShopSeoContext::resolve((string) ($page['description'] ?? ''));
        $context['faqs'] = [
            [
                'question' => 'Is RepairPal the same as Google reviews?',
                'answer' => 'No. Google reviews stay on Google. RepairPal collects its own reviews through its platform. Both are independent signals.',
            ],
            [
                'question' => 'Do I have to book through RepairPal?',
                'answer' => 'No. You can request service on this site, call or text the shop, or use RepairPal if you prefer their estimate flow.',
            ],
            [
                'question' => 'How do I verify LugsNPlugs is still RepairPal Certified?',
                'answer' => 'Open the official RepairPal profile for LugsNPlugs. That listing is the third-party source of record for certification status.',
            ],
        ];

        return $this->build(
            title: sprintf('%s — %s', $context['name'], $page['title'] ?? 'RepairPal Certified'),
            description: (string) ($page['description'] ?? $context['description']),
            path: '/repairpal-certified',
            schemaTypes: ['AutoRepair', 'FAQPage'],
            schemaContext: $context,
            breadcrumbs: [
                ['name' => 'Home', 'url' => PublicMarketingUrl::absolute('/')],
                ['name' => 'RepairPal', 'url' => PublicMarketingUrl::absolute('/repairpal')],
                ['name' => 'Certified', 'url' => PublicMarketingUrl::absolute('/repairpal-certified')],
            ],
        );
    }

    public function forRepairPalReviewsPage(): SeoPageMeta
    {
        $page = config('public_seo.repairpal_reviews', []);
        $context = ShopSeoContext::resolve((string) ($page['description'] ?? ''));

        return $this->build(
            title: sprintf('%s — %s', $context['name'], $page['title'] ?? 'RepairPal Reviews'),
            description: (string) ($page['description'] ?? $context['description']),
            path: '/repairpal-reviews',
            schemaTypes: ['AutoRepair'],
            schemaContext: $context,
            breadcrumbs: [
                ['name' => 'Home', 'url' => PublicMarketingUrl::absolute('/')],
                ['name' => 'RepairPal', 'url' => PublicMarketingUrl::absolute('/repairpal')],
                ['name' => 'Reviews', 'url' => PublicMarketingUrl::absolute('/repairpal-reviews')],
            ],
        );
    }

    public function forRepairPalWarrantyPage(): SeoPageMeta
    {
        $page = config('public_seo.repairpal_warranty', []);
        $context = ShopSeoContext::resolve((string) ($page['description'] ?? ''));
        $context['faqs'] = [
            [
                'question' => 'Is every repair covered by the RepairPal warranty?',
                'answer' => 'No. Qualifying repairs are confirmed on your estimate. Ask your advisor if you are unsure before authorizing work.',
            ],
            [
                'question' => 'What is the RepairPal nationwide warranty period?',
                'answer' => 'The RepairPal Certified warranty is 12 months / 12,000 miles on qualifying parts and labor — whichever comes first. LugsNPlugs also offers a separate shop warranty of 24 months / 24,000 miles on qualifying work, where applicable.',
            ],
        ];

        return $this->build(
            title: sprintf('%s — %s', $context['name'], $page['title'] ?? 'RepairPal Nationwide Warranty'),
            description: (string) ($page['description'] ?? $context['description']),
            path: '/repairpal-warranty',
            schemaTypes: ['AutoRepair', 'FAQPage'],
            schemaContext: $context,
            breadcrumbs: [
                ['name' => 'Home', 'url' => PublicMarketingUrl::absolute('/')],
                ['name' => 'RepairPal', 'url' => PublicMarketingUrl::absolute('/repairpal')],
                ['name' => 'Warranty', 'url' => PublicMarketingUrl::absolute('/repairpal-warranty')],
            ],
        );
    }

    public function forPrivacyPage(): SeoPageMeta
    {
        $privacy = config('public_seo.privacy', []);
        $context = ShopSeoContext::resolve((string) ($privacy['description'] ?? ''));

        return $this->build(
            title: sprintf('%s — %s', $context['name'], $privacy['title'] ?? 'Privacy Policy'),
            description: (string) ($privacy['description'] ?? $context['description']),
            path: '/privacy',
            schemaTypes: ['AutoRepair'],
            schemaContext: $context,
            breadcrumbs: [
                ['name' => 'Home', 'url' => PublicMarketingUrl::absolute('/')],
                ['name' => 'Privacy', 'url' => PublicMarketingUrl::absolute('/privacy')],
            ],
        );
    }

    public function forTermsPage(): SeoPageMeta
    {
        $terms = config('public_seo.terms', []);
        $context = ShopSeoContext::resolve((string) ($terms['description'] ?? ''));

        return $this->build(
            title: sprintf('%s — %s', $context['name'], $terms['title'] ?? 'Terms of Use'),
            description: (string) ($terms['description'] ?? $context['description']),
            path: '/terms',
            schemaTypes: ['AutoRepair'],
            schemaContext: $context,
            breadcrumbs: [
                ['name' => 'Home', 'url' => PublicMarketingUrl::absolute('/')],
                ['name' => 'Terms', 'url' => PublicMarketingUrl::absolute('/terms')],
            ],
        );
    }

    /**
     * @param  array{title: string, meta_description: string, path: string, faq?: list<array{question: string, answer: string}>}  $problem
     */
    public function forCommonProblem(array $problem): SeoPageMeta
    {
        $context = ShopSeoContext::resolve($problem['meta_description']);

        if (($problem['faq'] ?? []) !== []) {
            $context['faqs'] = $problem['faq'];
        }

        $title = filled($problem['seo_title'] ?? null)
            ? (string) $problem['seo_title']
            : sprintf('%s — %s | Colorado Springs', $context['name'], $problem['page_title'] ?? $problem['title']);

        $featuredMedia = CommonProblemFeaturedMedia::primaryForDisplay(
            is_array($problem['featured_media'] ?? null) ? $problem['featured_media'] : null,
        );
        $ogImage = $featuredMedia !== null
            ? PublicMarketingUrl::absoluteIfRelative($featuredMedia['url_large'] ?? $featuredMedia['url'])
            : null;

        return $this->build(
            title: $title,
            description: $problem['meta_description'],
            path: $problem['path'],
            schemaTypes: ['AutoRepair', 'FAQPage'],
            schemaContext: $context,
            breadcrumbs: [
                ['name' => 'Home', 'url' => PublicMarketingUrl::absolute('/')],
                ['name' => 'Common Problems', 'url' => PublicMarketingUrl::absolute('/common-problems')],
                ['name' => $problem['title'], 'url' => PublicMarketingUrl::absolute($problem['path'])],
            ],
            ogImage: $ogImage,
        );
    }

    public function forContent(GrowthContent $content, array $schemaContext = []): SeoPageMeta
    {
        $description = (string) ($content->metadata['meta_description'] ?? $content->title);
        $context = array_merge(ShopSeoContext::resolve($description), $schemaContext);

        return $this->build(
            title: $content->title,
            description: $description,
            path: $content->path,
            indexable: $content->indexable,
            schemaTypes: (array) ($content->metadata['schema_types'] ?? ['AutoRepair']),
            schemaContext: $context,
            breadcrumbs: (array) ($content->metadata['breadcrumbs'] ?? []),
            ogImage: $context['image'] ?? null,
        );
    }
}
