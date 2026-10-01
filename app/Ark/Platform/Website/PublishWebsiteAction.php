<?php

namespace App\Ark\Platform\Website;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PublishWebsiteAction
{
    public function execute(WebsiteSite $site, int $expectedRevision, User $actor): WebsitePublication
    {
        return DB::transaction(function () use ($site, $expectedRevision, $actor): WebsitePublication {
            /** @var WebsiteSite $locked */
            $locked = WebsiteSite::query()->whereKey($site->id)->lockForUpdate()->firstOrFail();

            if ($locked->writing_authority !== WebsiteWritingAuthority::CoreLive) {
                throw new WebsiteAuthoritySwitchException('authority');
            }

            /** @var WebsiteDraft $draft */
            $draft = WebsiteDraft::query()
                ->where('website_site_id', $locked->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $draft->revision !== $expectedRevision) {
                throw new WebsiteDraftRevisionConflictException($expectedRevision, (int) $draft->revision);
            }

            DB::table('shop_settings')->orderBy('id')->lockForUpdate()->get(['id']);
            $core = CoreWebsiteDocument::read();
            $draftDocument = is_array($draft->document) ? $draft->document : [];
            $draftHash = WebsiteDocumentHash::hash($draftDocument);

            if (! hash_equals($core['hash'], $draftHash)) {
                throw new WebsiteDraftDivergesFromCoreException($draftHash, $core['hash']);
            }

            WebsitePublication::query()
                ->where('website_site_id', $locked->id)
                ->where('is_current', true)
                ->lockForUpdate()
                ->update(['is_current' => false]);

            $version = ((int) WebsitePublication::query()
                ->where('website_site_id', $locked->id)
                ->max('version')) + 1;

            $publication = WebsitePublication::query()->create([
                'website_site_id' => $locked->id,
                'version' => $version,
                'document' => $core['document'],
                'content_hash' => $core['hash'],
                'is_current' => true,
                'published_at' => now(),
                'published_by_user_id' => $actor->id,
                'source_draft_revision' => $expectedRevision,
            ]);

            $nextRevision = $expectedRevision + 1;
            $draft->update([
                'revision' => $nextRevision,
                'updated_by_user_id' => $actor->id,
            ]);

            WebsiteDraftRevision::query()->create([
                'website_draft_id' => $draft->id,
                'revision' => $nextRevision,
                'document' => $core['document'],
                'action' => WebsiteDraftRevisionAction::Publish,
                'core_hash' => $core['hash'],
                'actor_user_id' => $actor->id,
                'meta' => [
                    'publication_id' => $publication->id,
                    'publication_version' => $version,
                ],
                'created_at' => now(),
            ]);

            $locked->refresh();
            if ($locked->writing_authority !== WebsiteWritingAuthority::CoreLive) {
                throw new WebsiteAuthoritySwitchException('authority');
            }

            return $publication->fresh() ?? $publication;
        });
    }
}
