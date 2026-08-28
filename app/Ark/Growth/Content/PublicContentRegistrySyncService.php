<?php

namespace App\Ark\Growth\Content;

use App\Ark\Operations\Leads\Public\CommonProblemAuthorityWordCount;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use App\Ark\Growth\PublicSurface\PublicMarketingUrl;

final class PublicContentRegistrySyncService
{
    public function __construct(
        private readonly ContentRegistry $registry,
    ) {}

    public function sync(): int
    {
        $count = 0;

        $home = config('public_seo.home', []);
        $this->registry->registerOrUpdate('home', [
            'template' => 'pages',
            'title' => (string) ($home['title_suffix'] ?? 'Auto Repair'),
            'path' => '/',
            'published_at' => now(),
            'indexable' => true,
            'priority' => 100,
            'metadata' => [
                'meta_description' => (string) ($home['description'] ?? ''),
                'schema_types' => ['AutoRepair', 'LocalBusiness'],
            ],
        ]);
        $count++;

        $this->registry->registerOrUpdate('common-problems-index', [
            'template' => 'common_problems',
            'title' => 'Common Car Problems',
            'path' => '/common-problems',
            'published_at' => now(),
            'indexable' => true,
            'priority' => 90,
            'metadata' => [
                'meta_description' => 'Symptoms, common causes, and when to have your vehicle checked.',
                'schema_types' => ['AutoRepair'],
            ],
        ]);
        $count++;

        foreach (CommonProblemRegistry::all() as $problem) {
            $this->registry->registerOrUpdate($problem['slug'], [
                'template' => 'common_problems',
                'title' => $problem['title'],
                'path' => $problem['path'],
                'published_at' => now(),
                'indexable' => true,
                'priority' => ($problem['tier'] ?? 2) === 1 ? 80 : (($problem['generated'] ?? false) ? 75 : 70),
                'metadata' => [
                    'meta_description' => $problem['meta_description'],
                    'schema_types' => ['AutoRepair', 'FAQPage'],
                    'body_word_count' => CommonProblemAuthorityWordCount::count($problem),
                    'generated' => (bool) ($problem['generated'] ?? false),
                ],
            ]);
            $count++;
        }

        return $count;
    }

    public function baseUrl(): string
    {
        return PublicMarketingUrl::baseUrl();
    }
}
