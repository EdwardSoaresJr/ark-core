<?php

namespace App\Ark\Website;

use App\Ark\Platform\Website\WebsiteDocumentHash;
use App\Ark\Platform\Website\WebsitePublication;
use App\Ark\Platform\Website\WebsiteSite;
use App\Ark\Website\Catalog\PublicWebsiteCatalog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Merges catalog content onto the existing site and moves public_host to the native ARK host.
 *
 * Dry-run does not write. Apply keeps the previous publication row so rollback does not rebuild it.
 */
final class WebsitePublicationCutover
{
    public function __construct(
        private readonly WebsitePublicationCatalogMerge $merge,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function plan(?string $fromHost, string $toHost): array
    {
        $toHost = WebsiteHosts::host($toHost);
        if ($toHost === null) {
            return $this->blocked('new_public_host is missing.');
        }

        $site = $this->site($fromHost, $toHost);
        if (! $site instanceof WebsiteSite) {
            return $this->blocked('No website site matched '.$this->describeHosts($fromHost, $toHost).'.');
        }

        $current = $this->currentPublication($site);
        if (! $current instanceof WebsitePublication) {
            return $this->blocked('Site '.$site->id.' has no current publication.');
        }

        $document = is_array($current->document) ? $current->document : [];
        $proposed = $this->merge->merge($document, PublicWebsiteCatalog::document());
        $proposedHash = WebsiteDocumentHash::hash($proposed);
        $warnings = $this->warnings($site, $toHost, $document, $proposed);
        $blocking = array_values(array_filter(
            $warnings,
            fn (string $warning): bool => str_starts_with($warning, 'blocked: '),
        ));

        return [
            'ok' => $blocking === [],
            'site_id' => $site->id,
            'old_public_host' => (string) $site->public_host,
            'new_public_host' => $toHost,
            'current_version' => (int) $current->version,
            'current_hash' => (string) $current->content_hash,
            'proposed_version' => (int) $current->version + ($current->content_hash === $proposedHash ? 0 : 1),
            'proposed_hash' => $proposedHash,
            'preserved_keys' => $this->preservedKeys($document),
            'catalog_keys_changed' => $this->changedCatalogKeys($document, $proposed),
            'common_problems_before' => $this->listCount($document, 'common_problems'),
            'common_problems_after' => $this->listCount($proposed, 'common_problems'),
            'shop_photos_before' => $this->listCount($document, 'shop_photos'),
            'shop_photos_after' => $this->listCount($proposed, 'shop_photos'),
            'composition_photos_before' => $this->listCount($document, 'composition_photos'),
            'composition_photos_after' => $this->listCount($proposed, 'composition_photos'),
            'customer_reviews_before' => $this->listCount($document, 'customer_reviews'),
            'customer_reviews_after' => $this->listCount($proposed, 'customer_reviews'),
            'reviews_before' => $this->listCount($document, 'reviews'),
            'reviews_after' => $this->listCount($proposed, 'reviews'),
            'preferred_canonical' => WebsiteHosts::preferredPublicHost($toHost),
            'warnings' => $warnings,
            'publication_id' => $current->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    public function apply(array $plan): void
    {
        if (($plan['ok'] ?? false) !== true) {
            throw new RuntimeException('Cutover plan is not safe to apply.');
        }

        DB::transaction(function () use ($plan): void {
            $site = WebsiteSite::query()->whereKey($plan['site_id'])->lockForUpdate()->first();
            if (! $site instanceof WebsiteSite) {
                throw new RuntimeException('Website site disappeared before apply.');
            }

            $fresh = $this->plan((string) $site->public_host, (string) $plan['new_public_host']);
            if (($fresh['ok'] ?? false) !== true || ($fresh['proposed_hash'] ?? '') !== ($plan['proposed_hash'] ?? '')) {
                throw new RuntimeException('Publication changed since the dry run. Run it again.');
            }

            if ((string) $site->public_host === (string) $plan['new_public_host']
                && (string) $fresh['current_hash'] === (string) $fresh['proposed_hash']) {
                return;
            }

            $current = $this->currentPublication($site);
            if (! $current instanceof WebsitePublication) {
                throw new RuntimeException('Current publication disappeared before apply.');
            }

            $document = is_array($current->document) ? $current->document : [];
            $proposed = $this->merge->merge($document, PublicWebsiteCatalog::document());
            $hash = WebsiteDocumentHash::hash($proposed);
            if ($hash !== $plan['proposed_hash']) {
                throw new RuntimeException('Merged document hash does not match the plan.');
            }

            $this->assertPreserved($document, $proposed);

            $occupant = WebsiteSite::query()
                ->whereRaw('lower(public_host) = ?', [$plan['new_public_host']])
                ->whereKeyNot($site->id)
                ->exists();
            if ($occupant) {
                throw new RuntimeException('Another site already uses '.$plan['new_public_host'].'.');
            }

            $site->update([
                'public_host' => $plan['new_public_host'],
                'acknowledged_core_hash' => $hash,
            ]);

            if ($current->content_hash === $hash) {
                return;
            }

            $current->update(['is_current' => false]);

            WebsitePublication::query()->create([
                'website_site_id' => $site->id,
                'version' => ((int) WebsitePublication::query()->where('website_site_id', $site->id)->max('version')) + 1,
                'document' => $proposed,
                'content_hash' => $hash,
                'is_current' => true,
                'published_at' => now(),
                'source_draft_revision' => $current->source_draft_revision,
            ]);
        });
    }

    public function revert(int $siteId, int $version, string $publicHost): void
    {
        $publicHost = WebsiteHosts::host($publicHost);
        if ($publicHost === null) {
            throw new RuntimeException('Rollback host is missing.');
        }

        DB::transaction(function () use ($siteId, $version, $publicHost): void {
            $site = WebsiteSite::query()->whereKey($siteId)->lockForUpdate()->first();
            $publication = WebsitePublication::query()
                ->where('website_site_id', $siteId)
                ->where('version', $version)
                ->lockForUpdate()
                ->first();

            if (! $site instanceof WebsiteSite || ! $publication instanceof WebsitePublication) {
                throw new RuntimeException('Rollback target was not found.');
            }

            $occupant = WebsiteSite::query()
                ->whereRaw('lower(public_host) = ?', [$publicHost])
                ->whereKeyNot($site->id)
                ->exists();
            if ($occupant) {
                throw new RuntimeException('Another site already uses '.$publicHost.'.');
            }

            WebsitePublication::query()
                ->where('website_site_id', $site->id)
                ->whereKeyNot($publication->id)
                ->update(['is_current' => false]);

            $publication->update(['is_current' => true]);
            $site->update([
                'public_host' => $publicHost,
                'acknowledged_core_hash' => $publication->content_hash,
            ]);
        });
    }

    private function site(?string $fromHost, string $toHost): ?WebsiteSite
    {
        $from = WebsiteHosts::host((string) $fromHost);
        if ($from !== null) {
            return WebsiteSite::query()->whereRaw('lower(public_host) = ?', [$from])->first();
        }

        foreach (WebsiteHosts::customDomains() as $entry) {
            if ($entry['site_host'] !== $toHost) {
                continue;
            }
            $custom = WebsiteSite::query()->whereRaw('lower(public_host) = ?', [$entry['domain']])->first();
            if ($custom instanceof WebsiteSite) {
                return $custom;
            }
        }

        return WebsiteSite::query()->whereRaw('lower(public_host) = ?', [$toHost])->first();
    }

    private function currentPublication(WebsiteSite $site): ?WebsitePublication
    {
        $publication = WebsitePublication::query()
            ->where('website_site_id', $site->id)
            ->where('is_current', true)
            ->first();

        return $publication instanceof WebsitePublication ? $publication : null;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $proposed
     * @return list<string>
     */
    private function warnings(WebsiteSite $site, string $toHost, array $current, array $proposed): array
    {
        $warnings = [];

        $occupant = WebsiteSite::query()
            ->whereRaw('lower(public_host) = ?', [$toHost])
            ->whereKeyNot($site->id)
            ->exists();
        if ($occupant) {
            $warnings[] = 'blocked: another site already uses '.$toHost.'.';
        }

        if (WebsiteSite::query()->count() !== 1) {
            $warnings[] = 'blocked: expected one website site, found '.WebsiteSite::query()->count().'.';
        }

        foreach (WebsitePublicationCatalogMerge::PUBLICATION_OWNED as $key) {
            if (! array_key_exists($key, $current)) {
                continue;
            }
            if (($proposed[$key] ?? null) !== $current[$key]) {
                $warnings[] = 'blocked: publication-owned key '.$key.' would change.';
            }
        }

        foreach (['shop_photos', 'composition_photos', 'customer_reviews', 'reviews'] as $key) {
            if ($this->listCount($proposed, $key) < $this->listCount($current, $key)) {
                $warnings[] = 'blocked: '.$key.' count would drop.';
            }
        }

        if ($this->listCount($proposed, 'common_problems') === 0) {
            $warnings[] = 'blocked: merged document has no common problems.';
        }

        if (WebsiteHosts::preferredPublicHost($toHost) === $toHost) {
            $warnings[] = 'preferred canonical is the native host. Set WEBSITE_CUSTOM_DOMAIN before cutover if lugsnplugs.com should stay canonical.';
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $proposed
     */
    private function assertPreserved(array $current, array $proposed): void
    {
        foreach (WebsitePublicationCatalogMerge::PUBLICATION_OWNED as $key) {
            if (! array_key_exists($key, $current)) {
                continue;
            }
            if (($proposed[$key] ?? null) !== $current[$key]) {
                throw new RuntimeException('Refusing to change publication-owned key '.$key.'.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    private function preservedKeys(array $document): array
    {
        return array_values(array_filter(
            WebsitePublicationCatalogMerge::PUBLICATION_OWNED,
            fn (string $key): bool => array_key_exists($key, $document),
        ));
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $proposed
     * @return list<string>
     */
    private function changedCatalogKeys(array $current, array $proposed): array
    {
        $changed = [];
        foreach (WebsitePublicationCatalogMerge::CATALOG_OWNED as $key) {
            if (($current[$key] ?? null) !== ($proposed[$key] ?? null)) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function listCount(array $document, string $key): int
    {
        $value = $document[$key] ?? [];

        return is_array($value) ? count($value) : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function blocked(string $warning): array
    {
        return [
            'ok' => false,
            'warnings' => ['blocked: '.$warning],
        ];
    }

    private function describeHosts(?string $fromHost, string $toHost): string
    {
        $from = WebsiteHosts::host((string) $fromHost);

        return $from !== null ? $from : $toHost;
    }
}
