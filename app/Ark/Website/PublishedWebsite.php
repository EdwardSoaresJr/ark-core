<?php

namespace App\Ark\Website;

use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Website\WebsitePublication;
use App\Ark\Platform\Website\WebsiteSite;

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
     * @return array{title: string, lede: string, sections: list<array{heading: string, body: string}>}
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

        return [
            'title' => trim((string) ($page['title'] ?? '')),
            'lede' => trim((string) ($page['lede'] ?? '')),
            'sections' => $sections,
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

        return $normalized;
    }

    public function financingLede(): string
    {
        $financing = $this->document['financing'] ?? [];

        return is_array($financing) ? trim((string) ($financing['lede'] ?? '')) : '';
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
}
