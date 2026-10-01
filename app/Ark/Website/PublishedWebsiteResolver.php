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
        if (str_starts_with($host, 'www.')) {
            return null;
        }

        if (WebsiteHosts::isCustomDomain($host)) {
            $native = WebsiteHosts::nativeHostForCustomDomain($host);
            if ($native === null) {
                return null;
            }

            return $this->siteByHost($native);
        }

        return $this->siteByHost($host);
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
