<?php

namespace App\Ark\Operations\Learn;

use App\Models\User;

final class LearnArkPreviewProjection
{
    /**
     * @return array{
     *     role: string,
     *     slug: string,
     *     title: string,
     *     summary: string,
     *     section_label: string,
     *     html: string,
     *     arkademy_url: string,
     * }|null
     */
    public static function for(User $user, string $roleKey, string $slug): ?array
    {
        $article = LearnArkCatalog::articleFor($user, $roleKey, $slug);

        if ($article === null) {
            return null;
        }

        return [
            'role' => $roleKey,
            'slug' => $slug,
            'title' => $article['title'],
            'summary' => $article['summary'],
            'section_label' => $article['section']->label,
            'html' => LearnArkPreviewHtml::render($roleKey, $article),
            'arkademy_url' => ArkademyUrls::pageUrlOrHome($roleKey, $slug),
        ];
    }
}
