<?php

namespace App\Ark\Website;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Telephony\TelephonyBusinessHoursLabel;
use App\Ark\Platform\Website\WebsitePublication;
use App\Ark\Platform\Website\WebsiteSite;
use App\Ark\Website\Catalog\PublicWebsiteCatalog;
use Illuminate\Support\Facades\Storage;

/**
 * Current published website for one public host.
 *
 * Presentation comes from the publication document. Shop identity comes from
 * ShopSettings at read time and is not copied into the document.
 */
final class PublishedWebsite
{
    /**
     * @param  array<string, mixed>  $document
     */
    public function __construct(
        public readonly WebsiteSite $site,
        public readonly WebsitePublication $publication,
        public readonly ShopSettings $shop,
        private readonly array $document,
    ) {}

    public function canonicalHost(): string
    {
        return WebsiteHosts::preferredPublicHost((string) $this->site->public_host);
    }

    public function canonicalUrl(string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');
        if ($path === '//') {
            $path = '/';
        }

        $host = $this->canonicalHost();

        return 'https://'.$host.($path === '/' ? '/' : $path);
    }

    public function headline(): string
    {
        return $this->string('headline');
    }

    public function lede(): string
    {
        return $this->string('positioning_lede');
    }

    public function localTagline(): string
    {
        return $this->string('local_tagline');
    }

    public function shopName(): string
    {
        $name = trim((string) ($this->shop->shop_name ?? ''));

        return $name !== '' ? $name : 'Shop';
    }

    public function phone(): string
    {
        return trim((string) ($this->shop->phone ?? ''));
    }

    public function phoneDisplay(): string
    {
        return PhoneNumber::display($this->phone()) ?? $this->phone();
    }

    public function email(): string
    {
        return trim((string) ($this->shop->email ?? ''));
    }

    public function address(): string
    {
        $parts = array_filter([
            trim((string) ($this->shop->address_line_1 ?? '')),
            trim((string) ($this->shop->address_line_2 ?? '')),
            trim(implode(' ', array_filter([
                trim((string) ($this->shop->city ?? '')),
                trim((string) ($this->shop->state ?? '')),
                trim((string) ($this->shop->postal_code ?? '')),
            ]))),
        ]);

        return implode(', ', $parts);
    }

    public function mapEmbedUrl(): ?string
    {
        $address = $this->address();
        if ($address === '') {
            return null;
        }

        $query = trim($this->shopName().' '.$address);
        if ($query === '') {
            return null;
        }

        return 'https://maps.google.com/maps?q='.rawurlencode($query).'&z=15&output=embed';
    }

    public function googleReviewsUrl(): string
    {
        return trim((string) ($this->shop->google_reviews_url ?? ''));
    }

    public function googleRating(): string
    {
        return $this->string('google_rating');
    }

    public function googleReviewCount(): int
    {
        return max(0, (int) ($this->document['google_review_count'] ?? 0));
    }

    /**
     * @return list<array{quote: string, attribution: string}>
     */
    public function reviews(): array
    {
        $reviews = $this->document['reviews'] ?? [];
        if (! is_array($reviews)) {
            return [];
        }

        $normalized = [];
        foreach ($reviews as $review) {
            if (! is_array($review)) {
                continue;
            }
            $quote = trim((string) ($review['quote'] ?? ''));
            if ($quote === '') {
                continue;
            }
            $normalized[] = [
                'quote' => $quote,
                'attribution' => trim((string) ($review['attribution'] ?? '')),
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    public function faqs(): array
    {
        $faqs = $this->document['contact_faqs'] ?? [];
        if (! is_array($faqs)) {
            return [];
        }

        $normalized = [];
        foreach ($faqs as $faq) {
            if (! is_array($faq)) {
                continue;
            }
            $question = trim((string) ($faq['question'] ?? ''));
            $answer = trim((string) ($faq['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }
            $corrected = $this->correctedContactAnswers()[$question] ?? null;
            if (is_string($corrected) && $corrected !== '') {
                $answer = $corrected;
            }
            $normalized[] = ['question' => $question, 'answer' => $answer];
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function problems(): array
    {
        $problems = $this->document['common_problems'] ?? [];

        return is_array($problems) ? array_values(array_filter($problems, 'is_array')) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function featuredProblems(): array
    {
        $featured = array_values(array_filter(
            $this->problems(),
            fn (array $problem): bool => (int) ($problem['tier'] ?? 0) === 1,
        ));

        return array_slice($featured, 0, 8);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function problem(string $slug): ?array
    {
        foreach ($this->problems() as $problem) {
            if ((string) ($problem['slug'] ?? '') === $slug) {
                return $problem;
            }
        }

        return null;
    }

    /**
     * @return array{title: string, lede: string, sections: list<array{heading: string, body: string}>, links: list<array{path: string, label: string}>}
     */
    public function page(string $key): array
    {
        $pages = $this->document['pages'] ?? [];
        $page = is_array($pages) ? ($pages[$key] ?? []) : [];
        if (! is_array($page)) {
            $page = [];
        }

        $sections = [];
        foreach ($page['sections'] ?? [] as $section) {
            if (! is_array($section)) {
                continue;
            }
            $heading = trim((string) ($section['heading'] ?? ''));
            $body = trim((string) ($section['body'] ?? ''));
            if ($heading === '' && $body === '') {
                continue;
            }
            $sections[] = ['heading' => $heading, 'body' => $body];
        }

        $links = [];
        foreach ($page['links'] ?? [] as $link) {
            if (! is_array($link)) {
                continue;
            }
            $path = trim((string) ($link['path'] ?? ''));
            $label = trim((string) ($link['label'] ?? ''));
            if ($path === '' || $label === '') {
                continue;
            }
            $links[] = ['path' => $path, 'label' => $label];
        }

        return [
            'title' => trim((string) ($page['title'] ?? '')),
            'lede' => trim((string) ($page['lede'] ?? '')),
            'sections' => $sections,
            'links' => $links,
        ];
    }

    /**
     * @return list<array{name: string, body: string, url: string}>
     */
    public function financingPrograms(): array
    {
        $financing = $this->document['financing'] ?? [];
        $programs = is_array($financing) ? ($financing['programs'] ?? []) : [];
        if (! is_array($programs)) {
            return [];
        }

        $normalized = [];
        foreach ($programs as $program) {
            if (! is_array($program)) {
                continue;
            }
            $name = trim((string) ($program['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $normalized[] = [
                'name' => $name,
                'body' => trim((string) ($program['body'] ?? '')),
                'url' => trim((string) ($program['url'] ?? '')),
            ];
        }

        $destinations = $this->financingDestinationUrls();
        foreach ($normalized as $index => $program) {
            $destination = $destinations[$program['name']] ?? '';
            if ($destination !== '') {
                $normalized[$index]['url'] = $destination;
            }
        }

        foreach ($normalized as $index => $program) {
            if ($program['name'] !== 'Synchrony Car Care' || ! $this->isGenericSynchronyUrl($program['url'])) {
                continue;
            }
            $merchantUrl = $this->catalogFinancingUrl('Synchrony Car Care');
            if ($merchantUrl !== '') {
                $normalized[$index]['url'] = $merchantUrl;
            }
        }

        return $normalized;
    }

    public function financingLede(): string
    {
        $financing = $this->document['financing'] ?? [];

        return is_array($financing) ? trim((string) ($financing['lede'] ?? '')) : '';
    }

    public function financingSummary(): ?string
    {
        $names = $this->financingOfferNames();
        $wisetack = in_array('Wisetack', $names, true);
        $synchrony = in_array('Synchrony Car Care', $names, true);

        if ($wisetack && $synchrony) {
            return 'If the job qualifies, Wisetack and Synchrony Car Care can spread the cost out.';
        }

        if ($synchrony) {
            return 'If the job qualifies, Synchrony Car Care can spread the cost out.';
        }

        if ($wisetack) {
            return 'If the job qualifies, Wisetack can spread the cost out.';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function financingOfferNames(): array
    {
        $trust = $this->document['trust_signals'] ?? [];
        if (is_array($trust) && array_key_exists('financing_available', $trust) && $trust['financing_available'] !== true) {
            return [];
        }

        $names = [];
        foreach ($this->financingPrograms() as $program) {
            if ($program['name'] === '' || $program['url'] === '') {
                continue;
            }
            $names[] = $program['name'];
        }

        return $names;
    }

    /**
     * @return array{quote: string, attribution: string}|null
     */
    public function reviewByAttribution(string $attribution): ?array
    {
        foreach ($this->reviews() as $review) {
            if ($review['attribution'] === $attribution) {
                return $review;
            }
        }

        return null;
    }

    public function hoursLabel(): string
    {
        return TelephonyBusinessHoursLabel::fromCallFlow();
    }

    public function streetLine(): string
    {
        return $this->shop->googleMatchedStreetAddress();
    }

    public function localityLine(): string
    {
        $city = trim((string) ($this->shop->city ?? ''));
        $region = trim(implode(' ', array_filter([
            trim((string) ($this->shop->state ?? '')),
            trim((string) ($this->shop->postal_code ?? '')),
        ])));

        if ($city === '') {
            return $region;
        }

        return $region === '' ? $city : $city.', '.$region;
    }

    /**
     * Shop photos matched by the alt text stored on the publication.
     *
     * @return array{bay: ?array{alt: string, url: string}, team: ?array{alt: string, url: string}, pressure: ?array{alt: string, url: string}, findings: ?array{alt: string, url: string}}
     */
    public function storyPhotos(): array
    {
        $wanted = [
            'bay' => 'Inside the LugsNPlugs service bays',
            'team' => 'The LugsNPlugs Team',
            'pressure' => 'Technician pressure testing a vehicle cooling system',
            'findings' => 'Technician verifying findings before advising',
        ];
        $byAlt = [];
        foreach ($this->shopPhotos() as $photo) {
            $byAlt[$photo['alt']] = $photo;
        }

        $picked = [];
        foreach ($wanted as $key => $alt) {
            $picked[$key] = $byAlt[$alt] ?? null;
        }

        return $picked;
    }

    /**
     * @return list<array{alt: string, url: string}>
     */
    public function shopPhotos(): array
    {
        $photos = $this->document['shop_photos'] ?? [];
        if (! is_array($photos)) {
            return [];
        }

        $normalized = [];
        foreach ($photos as $photo) {
            if (! is_array($photo)) {
                continue;
            }
            $path = trim((string) ($photo['path'] ?? ''));
            $alt = trim((string) ($photo['alt'] ?? ''));
            if ($path === '' || $alt === '') {
                continue;
            }
            $normalized[] = [
                'alt' => $alt,
                'url' => Storage::disk('public')->url($path),
            ];
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    public function serviceNames(): array
    {
        $services = $this->document['shop_services'] ?? [];
        if (! is_array($services)) {
            return [];
        }

        $names = [];
        foreach ($services as $service) {
            if (is_string($service)) {
                $name = trim($service);
            } elseif (is_array($service)) {
                $name = trim((string) ($service['name'] ?? $service['title'] ?? $service['label'] ?? ''));
            } else {
                continue;
            }
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return array{title: string, description: string}
     */
    public function seo(string $key): array
    {
        $seo = $this->document['seo'] ?? [];
        $entry = is_array($seo) ? ($seo[$key] ?? []) : [];
        if (! is_array($entry)) {
            $entry = [];
        }

        $title = trim((string) ($entry['title'] ?? ''));
        if ($title === '') {
            $title = $this->shopName();
        }

        return [
            'title' => $title,
            'description' => trim((string) ($entry['description'] ?? '')),
        ];
    }

    private function string(string $key): string
    {
        return trim((string) ($this->document[$key] ?? ''));
    }

    /**
     * @return array<string, string>
     */
    private function correctedContactAnswers(): array
    {
        $questions = [
            'Do you accept customer-supplied parts?',
            'Do you perform inspections?',
            'What forms of payment do you accept?',
        ];

        $answers = [];
        foreach (PublicWebsiteCatalog::document()['contact_faqs'] ?? [] as $faq) {
            if (! is_array($faq)) {
                continue;
            }
            $question = trim((string) ($faq['question'] ?? ''));
            $answer = trim((string) ($faq['answer'] ?? ''));
            if ($answer !== '' && in_array($question, $questions, true)) {
                $answers[$question] = $answer;
            }
        }

        return $answers;
    }

    /**
     * @return array<string, string>
     */
    private function financingDestinationUrls(): array
    {
        $trust = $this->document['trust_signals'] ?? [];
        if (! is_array($trust)) {
            return [];
        }

        $urls = [
            'Wisetack' => trim((string) ($trust['wisetack_url'] ?? '')),
            'Synchrony Car Care' => trim((string) ($trust['synchrony_url'] ?? '')),
        ];

        return array_filter($urls, fn (string $url): bool => $url !== '');
    }

    private function catalogFinancingUrl(string $name): string
    {
        foreach (PublicWebsiteCatalog::document()['financing']['programs'] ?? [] as $program) {
            if (! is_array($program) || trim((string) ($program['name'] ?? '')) !== $name) {
                continue;
            }

            return trim((string) ($program['url'] ?? ''));
        }

        return '';
    }

    private function isGenericSynchronyUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');

        return in_array($host, ['www.synchrony.com', 'synchrony.com', 'www.mysynchrony.com', 'mysynchrony.com'], true)
            && ($path === '' || str_contains($path, '/financing/car-care/prospecting') || str_contains($path, '/merchants/car-care-financing'));
    }
}
