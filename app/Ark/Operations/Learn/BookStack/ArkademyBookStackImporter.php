<?php

namespace App\Ark\Operations\Learn\BookStack;

use App\Ark\Operations\Learn\LearnArkCatalog;
use App\Ark\Operations\Learn\LearnArkCurriculum;
use App\Ark\Operations\Learn\LearnArticleKey;
use App\Models\ArkademyContentRegistry;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;

final class ArkademyBookStackImporter
{
    /** @var array<string, array{page_id: int, url: string, html: string}> */
    private array $pageUrlMap = [];

    public function __construct(
        private readonly BookStackApiClient $client,
        private readonly ArkademyArticleHtmlExporter $exporter,
    ) {}

    public function import(bool $dryRun, OutputStyle $output): ArkademyImportReport
    {
        $report = new ArkademyImportReport;
        $catalog = LearnArkCatalog::articlesByRole();
        $roleBooks = (array) config('bookstack.role_books');

        if ($dryRun) {
            foreach ($catalog as $roleKey => $articles) {
                foreach ($articles as $article) {
                    $legacyKey = LearnArticleKey::make($roleKey, $article['slug']);
                    $report->articles[] = $legacyKey;
                    $output->writeln("[dry-run] {$legacyKey} → {$article['title']}");
                }
            }

            $report->shelfName = (string) config('bookstack.shelf_name');
            $report->bookCount = count($roleBooks);
            $report->articleCount = count($report->articles);

            return $report;
        }

        $shelfId = $this->ensureShelf($output);
        $bookIds = $this->ensureBooks($roleBooks, $output);
        $this->linkShelfToBooks($shelfId, array_values($bookIds), $output);

        foreach ($catalog as $roleKey => $articles) {
            $bookId = $bookIds[$roleKey] ?? null;

            if ($bookId === null) {
                throw new \RuntimeException("Missing BookStack book for role [{$roleKey}].");
            }

            foreach ($articles as $article) {
                $legacyKey = LearnArticleKey::make($roleKey, $article['slug']);
                $page = $this->importArticle($bookId, $roleKey, $article, $output);
                $report->articles[] = $legacyKey;
                $report->importedPages++;
                $this->pageUrlMap[$legacyKey] = [
                    'page_id' => (int) $page['id'],
                    'url' => $this->pageUrl($page),
                    'html' => (string) ($page['raw_html'] ?? $page['html'] ?? ''),
                ];
                usleep(150_000);
            }
        }

        $this->rewriteInternalLinks($output);
        $report->stalePagesRemoved = $this->pruneStalePages($catalog, $output);
        $report->shelfName = (string) config('bookstack.shelf_name');
        $report->bookCount = count($bookIds);
        $report->articleCount = count($report->articles);

        return $report;
    }

    private function ensureShelf(OutputStyle $output): int
    {
        $name = (string) config('bookstack.shelf_name');
        $existing = collect($this->client->listShelves())->firstWhere('name', $name);

        if ($existing !== null) {
            $output->writeln("Shelf exists: {$name} (#{$existing['id']})");

            return (int) $existing['id'];
        }

        $created = $this->client->createShelf([
            'name' => $name,
            'description' => (string) config('bookstack.shelf_description'),
        ]);

        $output->writeln("Created shelf: {$name} (#{$created['id']})");

        $this->register('bookshelf', (int) $created['id'], null, $name);

        return (int) $created['id'];
    }

    /**
     * @param  array<string, array{name: string, description: string}>  $roleBooks
     * @return array<string, int>
     */
    private function ensureBooks(array $roleBooks, OutputStyle $output): array
    {
        $existingBooks = collect($this->client->listBooks());
        $bookIds = [];

        foreach ($roleBooks as $roleKey => $bookMeta) {
            $existing = $existingBooks->firstWhere('name', $bookMeta['name']);

            if ($existing !== null) {
                $output->writeln("Book exists: {$bookMeta['name']} (#{$existing['id']})");
                $bookIds[$roleKey] = (int) $existing['id'];

                continue;
            }

            $created = $this->client->createBook([
                'name' => $bookMeta['name'],
                'description' => $bookMeta['description'],
                'tags' => [
                    ['name' => 'base-content', 'value' => ''],
                    ['name' => $roleKey, 'value' => ''],
                ],
            ]);

            $output->writeln("Created book: {$bookMeta['name']} (#{$created['id']})");
            $bookIds[$roleKey] = (int) $created['id'];
            $this->register('book', (int) $created['id'], null, $bookMeta['name']);
        }

        return $bookIds;
    }

    /**
     * @param  list<int>  $bookIds
     */
    private function linkShelfToBooks(int $shelfId, array $bookIds, OutputStyle $output): void
    {
        $this->client->updateShelf($shelfId, [
            'name' => (string) config('bookstack.shelf_name'),
            'books' => $bookIds,
        ]);

        $output->writeln('Linked '.count($bookIds).' books to shelf.');
    }

    /**
     * @param  array{slug: string, title: string, summary: string, view: string}  $article
     * @return array<string, mixed>
     */
    private function importArticle(int $bookId, string $roleKey, array $article, OutputStyle $output): array
    {
        $legacyKey = LearnArticleKey::make($roleKey, $article['slug']);
        $html = $this->exporter->render($roleKey, $article);
        $html = $this->prependSummary($article['summary'], $html);
        $tags = $this->exporter->tagsFor($roleKey, $article['slug']);

        $registry = ArkademyContentRegistry::findByLegacyKey($legacyKey);

        if ($registry !== null) {
            try {
                $page = $this->client->updatePage((int) $registry->bookstack_id, [
                    'name' => $article['title'],
                    'html' => $html,
                    'tags' => $tags,
                ]);
                $output->writeln("Updated page: {$legacyKey} (#{$page['id']})");
            } catch (\Throwable $exception) {
                if (! str_contains($exception->getMessage(), '404')) {
                    throw $exception;
                }

                $page = $this->client->createPage([
                    'book_id' => $bookId,
                    'name' => $article['title'],
                    'html' => $html,
                    'tags' => $tags,
                ]);
                $output->writeln("Recreated page: {$legacyKey} (#{$page['id']})");
            }
        } else {
            $page = $this->client->createPage([
                'book_id' => $bookId,
                'name' => $article['title'],
                'html' => $html,
                'tags' => $tags,
            ]);
            $output->writeln("Created page: {$legacyKey} (#{$page['id']})");
        }

        $pageId = (int) $page['id'];
        $html = (string) ($page['raw_html'] ?? $page['html'] ?? $html);

        foreach ($this->exporter->mediaFilesFor($roleKey, $article['slug']) as $mediaFile) {
            $uploaded = $this->client->uploadGalleryImage($pageId, $mediaFile['path'], $mediaFile['name']);
            $imageUrl = (string) ($uploaded['url'] ?? '');

            if ($imageUrl !== '') {
                foreach ($mediaFile['replace_urls'] as $oldUrl) {
                    $html = str_replace($oldUrl, $imageUrl, $html);
                }
            }
        }

        if ($html !== (string) ($page['raw_html'] ?? $page['html'] ?? '')) {
            $page = $this->client->updatePage($pageId, [
                'html' => $html,
            ]);
        }

        $this->register(
            'page',
            (int) $page['id'],
            $legacyKey,
            $article['title'],
            $this->pageUrl($page),
        );

        return $page;
    }

    private function rewriteInternalLinks(OutputStyle $output): void
    {
        $updates = 0;

        foreach ($this->pageUrlMap as $sourceLegacyKey => $sourceMeta) {
            $html = $sourceMeta['html'] ?? '';

            if ($html === '') {
                continue;
            }

            $rewritten = $this->rewriteLinksInHtml($html);

            if ($rewritten === $html) {
                continue;
            }

            $this->client->updatePage($sourceMeta['page_id'], [
                'html' => $rewritten,
            ]);
            $updates++;
            usleep(150_000);
        }

        $output->writeln("Rewrote internal links on {$updates} pages.");
    }

    private function rewriteLinksInHtml(string $html): string
    {
        foreach ($this->pageUrlMap as $legacyKey => $meta) {
            $parsed = LearnArticleKey::parse($legacyKey);

            if ($parsed === null) {
                continue;
            }

            [$roleKey, $slug] = $parsed;
            $targetUrl = $meta['url'] ?? null;

            if ($targetUrl === null) {
                continue;
            }

            $patterns = [
                route('operations.learn.show', ['role' => $roleKey, 'article' => $slug]),
                url('/app/learn/'.$roleKey.'/'.$slug),
                '/app/learn/'.$roleKey.'/'.$slug,
            ];

            foreach ($patterns as $pattern) {
                $html = str_replace($pattern, $targetUrl, $html);
            }
        }

        return $html;
    }

    /**
     * @param  array<string, list<array{slug: string, title: string, summary: string, view: string}>>  $catalog
     */
    private function pruneStalePages(array $catalog, OutputStyle $output): int
    {
        $currentLegacyKeys = [];

        foreach ($catalog as $roleKey => $articles) {
            foreach ($articles as $article) {
                $currentLegacyKeys[] = LearnArticleKey::make($roleKey, $article['slug']);
            }
        }

        $stale = ArkademyContentRegistry::query()
            ->where('source_type', 'page')
            ->whereNotNull('legacy_key')
            ->whereNotIn('legacy_key', $currentLegacyKeys)
            ->get();

        $removed = 0;

        foreach ($stale as $registry) {
            try {
                $this->client->deletePage((int) $registry->bookstack_id);
            } catch (\Throwable $exception) {
                $output->writeln("<comment>Could not delete BookStack page #{$registry->bookstack_id} ({$registry->legacy_key}): {$exception->getMessage()}</comment>");

                continue;
            }

            $registry->delete();
            $output->writeln("Removed stale page: {$registry->legacy_key} (#{$registry->bookstack_id})");
            $removed++;
            usleep(150_000);
        }

        return $removed;
    }

    private function prependSummary(string $summary, string $html): string
    {
        if ($summary === '') {
            return $html;
        }

        return '<p><em>'.e($summary).'</em></p>'.$html;
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function pageUrl(array $page): string
    {
        $bookSlug = $this->bookSlugForPage($page);
        $pageSlug = (string) ($page['slug'] ?? Str::slug((string) ($page['name'] ?? 'page')));

        return rtrim((string) config('bookstack.base_url'), '/')."/books/{$bookSlug}/page/{$pageSlug}";
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function bookSlugForPage(array $page): string
    {
        $bookId = (int) ($page['book_id'] ?? 0);
        $book = collect($this->client->listBooks())->firstWhere('id', $bookId);

        return (string) ($book['slug'] ?? 'book');
    }

    private function register(
        string $sourceType,
        int $bookstackId,
        ?string $legacyKey,
        ?string $title,
        ?string $bookstackUrl = null,
    ): void {
        ArkademyContentRegistry::query()->updateOrCreate(
            [
                'source_type' => $sourceType,
                'bookstack_id' => $bookstackId,
            ],
            [
                'visibility' => 'base',
                'legacy_key' => $legacyKey,
                'title' => $title,
                'bookstack_url' => $bookstackUrl,
                'notes' => $legacyKey !== null
                    ? 'content_version='.LearnArkCurriculum::articleContentVersion($legacyKey)
                    : null,
            ],
        );
    }
}
