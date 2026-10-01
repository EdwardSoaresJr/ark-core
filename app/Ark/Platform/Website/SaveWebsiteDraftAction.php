<?php

namespace App\Ark\Platform\Website;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SaveWebsiteDraftAction
{
    /**
     * @param  array<string, mixed>  $document
     */
    public function execute(
        WebsiteSite $site,
        array $document,
        int $expectedRevision,
        User $actor,
    ): WebsiteDraft {
        return DB::transaction(function () use ($site, $document, $expectedRevision, $actor): WebsiteDraft {
            /** @var WebsiteDraft $draft */
            $draft = WebsiteDraft::query()
                ->where('website_site_id', $site->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $draft->revision !== $expectedRevision) {
                throw new WebsiteDraftRevisionConflictException($expectedRevision, (int) $draft->revision);
            }

            $this->assertCoreHashAcknowledged($site);

            $nextRevision = (int) $draft->revision + 1;

            $draft->update([
                'document' => $document,
                'revision' => $nextRevision,
                'updated_by_user_id' => $actor->id,
            ]);

            WebsiteDraftRevision::query()->create([
                'website_draft_id' => $draft->id,
                'revision' => $nextRevision,
                'document' => $document,
                'action' => WebsiteDraftRevisionAction::Edit,
                'core_hash' => $site->acknowledged_core_hash,
                'actor_user_id' => $actor->id,
                'meta' => null,
                'created_at' => now(),
            ]);

            return $draft->fresh() ?? $draft;
        });
    }

    private function assertCoreHashAcknowledged(WebsiteSite $site): void
    {
        $live = CoreWebsiteDocument::read();
        $acknowledged = (string) ($site->acknowledged_core_hash ?? '');

        if ($acknowledged !== '' && $live['hash'] === $acknowledged) {
            return;
        }

        throw new WebsiteDraftConflictException(
            acknowledgedCoreHash: $acknowledged,
            liveCoreHash: $live['hash'],
            liveDocument: $live['document'],
        );
    }
}
