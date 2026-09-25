<?php

namespace App\Ark\Website;

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Website\WebsitePublication;
use App\Ark\Platform\Website\WebsiteSite;
use Illuminate\Http\Request;

final class PublishedWebsiteResolver
{
    public function forRequest(Request $request): ?PublishedWebsite
    {
        return $this->forHost($request->getHost());
    }

    public function forHost(string $host): ?PublishedWebsite
    {
        $host = strtolower(trim($host));
        if ($host === '' || str_contains($host, '/') || str_contains($host, ' ')) {
            return null;
        }

        $site = $this->siteForHost($host);

        if (! $site instanceof WebsiteSite) {
            return null;
        }

        $publication = WebsitePublication::query()
            ->where('website_site_id', $site->id)
            ->where('is_current', true)
            ->first();

        if (! $publication instanceof WebsitePublication) {
            return null;
        }

        $document = is_array($publication->document) ? $publication->document : [];

        return new PublishedWebsite($site, $publication, ShopSettings::current(), $document);
    }

    private function siteForHost(string $host): ?WebsiteSite
    {
        $canonical = strtolower(trim((string) config('surfaces.public')));
        $aliases = $this->aliases();

        if ($canonical !== '' && ($host === $canonical || in_array($host, $aliases, true))) {
            return $this->siteByHost($canonical);
        }

        return $this->siteByHost($host);
    }

    /**
     * @return list<string>
     */
    private function aliases(): array
    {
        $canonical = strtolower(trim((string) config('surfaces.public')));
        $aliases = [];

        foreach ((array) config('surfaces.public_aliases', []) as $alias) {
            $alias = strtolower(trim((string) $alias));
            if ($alias === '' || $alias === $canonical || str_contains($alias, '/') || str_contains($alias, ' ')) {
                continue;
            }
            $aliases[] = $alias;
        }

        return array_values(array_unique($aliases));
    }

    private function siteByHost(string $host): ?WebsiteSite
    {
        if ($host === '') {
            return null;
        }

        $site = WebsiteSite::query()
            ->whereRaw('lower(public_host) = ?', [$host])
            ->first();

        return $site instanceof WebsiteSite ? $site : null;
    }
}
