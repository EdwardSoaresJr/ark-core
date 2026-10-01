<?php

namespace App\Ark\Platform\Website;

use App\Ark\Platform\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ImportWebsiteDraftFromCoreAction
{
    public function execute(Shop $shop, ?User $actor = null, ?string $publicHost = null): WebsiteSite
    {
        return DB::transaction(function () use ($shop, $actor, $publicHost): WebsiteSite {
            $core = CoreWebsiteDocument::read();

            $site = WebsiteSite::query()->firstOrNew([
                'platform_shop_id' => $shop->id,
            ]);

            if ($site->exists && $site->draft()->exists()) {
                throw new InvalidArgumentException(
                    'Website site already imported for this Platform shop. Re-import is not automatic.',
                );
            }

            $site->fill([
                'public_host' => $publicHost,
                'writing_authority' => WebsiteWritingAuthority::CoreLive,
                'acknowledged_core_hash' => $core['hash'],
                'imported_at' => now(),
                'management_enabled' => true,
            ]);
            $site->save();

            $draft = WebsiteDraft::query()->create([
                'website_site_id' => $site->id,
                'document' => $core['document'],
                'revision' => 1,
                'updated_by_user_id' => $actor?->id,
            ]);

            WebsiteDraftRevision::query()->create([
                'website_draft_id' => $draft->id,
                'revision' => 1,
                'document' => $core['document'],
                'action' => WebsiteDraftRevisionAction::Import,
                'core_hash' => $core['hash'],
                'actor_user_id' => $actor?->id,
                'meta' => [
                    'platform_shop_id' => $shop->id,
                    'public_host' => $publicHost,
                ],
                'created_at' => now(),
            ]);

            return $site->fresh(['draft', 'shop']) ?? $site;
        });
    }
}
