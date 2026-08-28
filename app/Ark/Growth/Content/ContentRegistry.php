<?php

namespace App\Ark\Growth\Content;

use App\Ark\Growth\Models\GrowthContent;
use Illuminate\Support\Collection;

final class ContentRegistry
{
    /**
     * @return Collection<int, GrowthContent>
     */
    public function publishedIndexable(): Collection
    {
        return GrowthContent::query()
            ->where('indexable', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('priority')
            ->get();
    }

    public function findByPath(string $path): ?GrowthContent
    {
        $path = '/'.trim($path, '/');
        if ($path === '/') {
            $path = '/';
        }

        return GrowthContent::query()->where('path', $path)->first();
    }

    public function findBySlug(string $slug): ?GrowthContent
    {
        return GrowthContent::query()->where('slug', $slug)->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function registerOrUpdate(string $slug, array $attributes): GrowthContent
    {
        return GrowthContent::query()->updateOrCreate(
            ['slug' => $slug],
            $attributes,
        );
    }

    /**
     * @return Collection<int, GrowthContent>
     */
    public function aboveRevenueThreshold(int $cents): Collection
    {
        return GrowthContent::query()
            ->where('revenue_cents', '>=', $cents)
            ->orderByDesc('revenue_cents')
            ->get();
    }
}
