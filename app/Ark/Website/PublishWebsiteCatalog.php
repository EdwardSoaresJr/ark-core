<?php

namespace App\Ark\Website;

use App\Ark\Platform\Shop;
use App\Ark\Platform\ShopStatus;
use App\Ark\Platform\Website\WebsiteDocumentHash;
use App\Ark\Platform\Website\WebsiteDraft;
use App\Ark\Platform\Website\WebsiteDraftRevision;
use App\Ark\Platform\Website\WebsiteDraftRevisionAction;
use App\Ark\Platform\Website\WebsitePublication;
use App\Ark\Platform\Website\WebsiteSite;
use App\Ark\Platform\Website\WebsiteWritingAuthority;
use App\Ark\Website\Catalog\PublicWebsiteCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes the imported public catalog into Core website_publications.
 *
 * Does not call Platform. platform_shops is the local site row the existing
 * website_sites foreign key requires. Rendering does not read that row.
 */
final class PublishWebsiteCatalog
{
    /**
     * @param  array<string, mixed>|null  $document
     */
    public function publish(string $host, ?array $document = null, bool $force = false): WebsitePublication
    {
        $host = strtolower(trim($host));
        $document ??= PublicWebsiteCatalog::document();
        $hash = WebsiteDocumentHash::hash($document);

        return DB::transaction(function () use ($host, $document, $hash, $force): WebsitePublication {
            $site = WebsiteSite::query()
                ->whereRaw('lower(public_host) = ?', [$host])
                ->lockForUpdate()
                ->first();

            if (! $site instanceof WebsiteSite) {
                $shop = Shop::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'slug' => 'public-website-'.Str::lower(Str::random(8)),
                    'display_name' => 'Public website',
                    'status' => ShopStatus::Active,
                ]);

                $site = WebsiteSite::query()->create([
                    'platform_shop_id' => $shop->id,
                    'public_host' => $host,
                    'writing_authority' => WebsiteWritingAuthority::CoreLive,
                    'acknowledged_core_hash' => $hash,
                    'imported_at' => now(),
                    'management_enabled' => false,
                ]);
            }

            $current = WebsitePublication::query()
                ->where('website_site_id', $site->id)
                ->where('is_current', true)
                ->lockForUpdate()
                ->first();

            if ($current instanceof WebsitePublication && ! $force && $current->content_hash === $hash) {
                return $current;
            }

            $draft = $site->draft;
            $revision = $draft instanceof WebsiteDraft ? ((int) $draft->revision + 1) : 1;

            if (! $draft instanceof WebsiteDraft) {
                $draft = WebsiteDraft::query()->create([
                    'website_site_id' => $site->id,
                    'document' => $document,
                    'revision' => $revision,
                ]);
            } else {
                $draft->update([
                    'document' => $document,
                    'revision' => $revision,
                ]);
            }

            WebsiteDraftRevision::query()->create([
                'website_draft_id' => $draft->id,
                'revision' => $revision,
                'document' => $document,
                'action' => WebsiteDraftRevisionAction::Import,
                'core_hash' => $hash,
                'meta' => ['source' => 'foundry-public-catalog-and-php-defaults'],
                'created_at' => now(),
            ]);

            if ($current instanceof WebsitePublication) {
                $current->update(['is_current' => false]);
            }

            $version = ((int) WebsitePublication::query()
                ->where('website_site_id', $site->id)
                ->max('version')) + 1;

            return WebsitePublication::query()->create([
                'website_site_id' => $site->id,
                'version' => $version,
                'document' => $document,
                'content_hash' => $hash,
                'is_current' => true,
                'published_at' => now(),
                'source_draft_revision' => $revision,
            ]);
        });
    }
}
