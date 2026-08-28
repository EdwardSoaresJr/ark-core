<?php

namespace App\Ark\Growth\Seo;

final readonly class SeoPageMeta
{
    /**
     * @param  list<array<string, mixed>>  $jsonLd
     * @param  list<array{name: string, url: string}>  $breadcrumbs
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public ?string $robots,
        public bool $indexable,
        public array $openGraph,
        public array $twitter,
        public array $jsonLd,
        public array $breadcrumbs,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'robots' => $this->robots,
            'indexable' => $this->indexable,
            'og' => $this->openGraph,
            'twitter' => $this->twitter,
            'json_ld' => $this->jsonLd,
            'breadcrumbs' => $this->breadcrumbs,
        ];
    }
}
