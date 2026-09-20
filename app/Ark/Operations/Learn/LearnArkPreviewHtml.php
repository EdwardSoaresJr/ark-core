<?php

namespace App\Ark\Operations\Learn;

use Illuminate\Support\Facades\View;

final class LearnArkPreviewHtml
{
    /**
     * @param  array{slug: string, title: string, summary: string, view: string}  $article
     */
    public static function render(string $roleKey, array $article): string
    {
        if (! View::exists(str_replace('.', '/', $article['view']))) {
            throw new \RuntimeException("Learn view missing [{$article['view']}].");
        }

        $html = view($article['view'])->render();

        return self::rewriteGuideLinks($html);
    }

    private static function rewriteGuideLinks(string $html): string
    {
        $html = preg_replace_callback(
            '~href="([^"]*?/app/learn/([a-z]+)/([a-z0-9-]+)(?:[#?][^"]*)?)"~',
            static function (array $matches): string {
                $role = $matches[2];
                $slug = $matches[3];

                return 'href="#" data-arkademy-guide="'.$role.':'.$slug.'"';
            },
            $html,
        ) ?? $html;

        return preg_replace_callback(
            '~href="(https://learn\.[^/]+/books/[^"]+)"~',
            static fn (array $matches): string => 'href="'.$matches[1].'" target="_blank" rel="noopener noreferrer"',
            $html,
        ) ?? $html;
    }
}
