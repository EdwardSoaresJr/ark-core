<?php

namespace App\Ark\Operations\Learn\BookStack;

use App\Ark\Operations\Learn\LearnArkCurriculum;
use App\Ark\Operations\Learn\LearnArkMedia;
use App\Ark\Operations\Learn\LearnArticleKey;
use App\Ark\Operations\Learn\LearnArticleMedia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

final class ArkademyArticleHtmlExporter
{
    /**
     * @param  array{slug: string, title: string, summary: string, view: string}  $article
     */
    public function render(string $roleKey, array $article): string
    {
        if (! View::exists(str_replace('.', '/', $article['view']))) {
            throw new \RuntimeException("Learn view missing [{$article['view']}].");
        }

        $html = view($article['view'])->render();

        return $this->sanitizeForBookStack($html);
    }

    /**
     * @return list<array{path: string, name: string, replace_urls: list<string>}>
     */
    public function mediaFilesFor(string $roleKey, string $slug): array
    {
        $articleKey = LearnArticleKey::make($roleKey, $slug);
        $files = [];

        foreach (LearnArticleMedia::query()->where('article_key', $articleKey)->get() as $media) {
            if (! $media->isImage() || $media->storage_path === null || $media->storage_path === '') {
                continue;
            }

            if (! Storage::disk('local')->exists($media->storage_path)) {
                continue;
            }

            $name = $media->original_name ?? basename($media->storage_path);
            $files[] = [
                'path' => Storage::disk('local')->path($media->storage_path),
                'name' => $name,
                'replace_urls' => [
                    route('operations.learn.media.show', $media),
                    LearnArkMedia::image($roleKey, $slug, $media->slot),
                ],
            ];
        }

        $legacyDir = public_path("images/learn/{$roleKey}/{$slug}");

        if (is_dir($legacyDir)) {
            foreach (glob($legacyDir.'/*') ?: [] as $path) {
                if (! is_file($path)) {
                    continue;
                }

                $name = basename($path);
                $files[] = [
                    'path' => $path,
                    'name' => $name,
                    'replace_urls' => [
                        asset("images/learn/{$roleKey}/{$slug}/{$name}"),
                        LearnArkMedia::image($roleKey, $slug, $name),
                    ],
                ];
            }
        }

        return $files;
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    public function tagsFor(string $roleKey, string $slug): array
    {
        $articleKey = LearnArticleKey::make($roleKey, $slug);
        $tags = [
            ['name' => 'base-content', 'value' => ''],
            ['name' => 'training', 'value' => ''],
            ['name' => $roleKey, 'value' => ''],
            ['name' => 'legacy-key', 'value' => $articleKey],
        ];

        if (LearnArkCurriculum::isRequired($articleKey)) {
            $tags[] = ['name' => 'required', 'value' => ''];
        }

        if (in_array($articleKey, LearnArkCurriculum::nextWaveArticleKeys(), true)) {
            $tags[] = ['name' => 'next-wave', 'value' => ''];
        }

        return $tags;
    }

    private function sanitizeForBookStack(string $html): string
    {
        $html = preg_replace('/\s+x-[a-z-]+="[^"]*"/', '', $html) ?? $html;
        $html = preg_replace('/\s+@[a-z]+="[^"]*"/', '', $html) ?? $html;
        $html = preg_replace('/\s+x-ref="[^"]*"/', '', $html) ?? $html;
        $html = preg_replace('/\s+x-show="[^"]*"/', '', $html) ?? $html;
        $html = preg_replace('/\s+x-text="[^"]*"/', '', $html) ?? $html;
        $html = preg_replace('/\s+x-init="[^"]*"/', '', $html) ?? $html;

        return trim($html);
    }
}
