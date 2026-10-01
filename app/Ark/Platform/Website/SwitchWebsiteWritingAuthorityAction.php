<?php

namespace App\Ark\Platform\Website;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SwitchWebsiteWritingAuthorityAction
{
    public function execute(WebsiteSite $site, User $actor, string $foundryImageDigest): WebsiteSite
    {
        if (! FoundryPublicationResolverRelease::matches($foundryImageDigest)) {
            throw new WebsiteAuthoritySwitchException('resolver');
        }

        return DB::transaction(function () use ($site, $actor): WebsiteSite {
            /** @var WebsiteSite $locked */
            $locked = WebsiteSite::query()->whereKey($site->id)->lockForUpdate()->firstOrFail();

            if ($locked->writing_authority !== WebsiteWritingAuthority::CoreLive) {
                throw new WebsiteAuthoritySwitchException('authority');
            }

            $current = WebsitePublication::query()
                ->where('website_site_id', $locked->id)
                ->where('is_current', true)
                ->lockForUpdate()
                ->get();

            if ($current->count() !== 1) {
                throw new WebsiteAuthoritySwitchException('publication');
            }

            /** @var WebsitePublication $publication */
            $publication = $current->first();
            $document = is_array($publication->document) ? $publication->document : [];
            $storedHash = (string) $publication->content_hash;

            if ($storedHash === '' || ! hash_equals($storedHash, WebsiteDocumentHash::hash($document))) {
                throw new WebsiteAuthoritySwitchException('hash');
            }

            DB::table('shop_settings')->orderBy('id')->lockForUpdate()->get(['id']);
            $core = CoreWebsiteDocument::read();

            if (! hash_equals($core['hash'], $storedHash)) {
                throw new WebsiteAuthoritySwitchException('hash');
            }

            $locked->writing_authority = WebsiteWritingAuthority::Platform;
            $locked->save();

            /** @var WebsiteDraft|null $draft */
            $draft = WebsiteDraft::query()
                ->where('website_site_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if ($draft !== null) {
                $nextRevision = (int) $draft->revision + 1;
                $draft->update([
                    'revision' => $nextRevision,
                    'updated_by_user_id' => $actor->id,
                ]);

                WebsiteDraftRevision::query()->create([
                    'website_draft_id' => $draft->id,
                    'revision' => $nextRevision,
                    'document' => $document,
                    'action' => WebsiteDraftRevisionAction::SwitchAuthority,
                    'core_hash' => $core['hash'],
                    'actor_user_id' => $actor->id,
                    'meta' => [
                        'publication_id' => $publication->id,
                        'publication_version' => $publication->version,
                    ],
                    'created_at' => now(),
                ]);
            }

            return $locked->fresh() ?? $locked;
        });
    }
}
