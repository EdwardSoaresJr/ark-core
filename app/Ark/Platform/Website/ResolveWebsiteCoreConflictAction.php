<?php

namespace App\Ark\Platform\Website;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ResolveWebsiteCoreConflictAction
{
    public function acceptCore(WebsiteSite $site, User $actor, int $expectedRevision): WebsiteDraft
    {
        return $this->resolve($site, $actor, $expectedRevision, WebsiteDraftRevisionAction::AcceptCore);
    }

    /**
     * Keep Platform draft; acknowledge only the observed live Core hash.
     */
    public function keepPlatform(
        WebsiteSite $site,
        User $actor,
        int $expectedRevision,
        string $observedCoreHash,
    ): WebsiteDraft {
        return $this->resolve(
            $site,
            $actor,
            $expectedRevision,
            WebsiteDraftRevisionAction::KeepPlatform,
            $observedCoreHash,
        );
    }

    private function resolve(
        WebsiteSite $site,
        User $actor,
        int $expectedRevision,
        WebsiteDraftRevisionAction $action,
        ?string $observedCoreHash = null,
    ): WebsiteDraft {
        return DB::transaction(function () use ($site, $actor, $expectedRevision, $action, $observedCoreHash): WebsiteDraft {
            /** @var WebsiteDraft $draft */
            $draft = WebsiteDraft::query()
                ->where('website_site_id', $site->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $draft->revision !== $expectedRevision) {
                throw new WebsiteDraftRevisionConflictException($expectedRevision, (int) $draft->revision);
            }

            $live = CoreWebsiteDocument::read();

            if ($action === WebsiteDraftRevisionAction::KeepPlatform) {
                if ($observedCoreHash === null || $observedCoreHash === '') {
                    throw new InvalidArgumentException('Keep Platform requires the observed Core hash.');
                }
                if ($observedCoreHash !== $live['hash']) {
                    throw new WebsiteDraftConflictException(
                        acknowledgedCoreHash: (string) ($site->acknowledged_core_hash ?? ''),
                        liveCoreHash: $live['hash'],
                        liveDocument: $live['document'],
                        message: 'Observed Core hash no longer matches live Core. Resolve against the current Core revision.',
                    );
                }
            }

            $nextRevision = (int) $draft->revision + 1;
            $document = $action === WebsiteDraftRevisionAction::AcceptCore
                ? $live['document']
                : (is_array($draft->document) ? $draft->document : []);
            $previousAcknowledged = (string) ($site->acknowledged_core_hash ?? '');

            $draft->update([
                'document' => $document,
                'revision' => $nextRevision,
                'updated_by_user_id' => $actor->id,
            ]);

            $site->update([
                'acknowledged_core_hash' => $live['hash'],
            ]);

            WebsiteDraftRevision::query()->create([
                'website_draft_id' => $draft->id,
                'revision' => $nextRevision,
                'document' => $document,
                'action' => $action,
                'core_hash' => $live['hash'],
                'actor_user_id' => $actor->id,
                'meta' => [
                    'previous_acknowledged_core_hash' => $previousAcknowledged,
                    'live_still_differs_from_draft' => $action === WebsiteDraftRevisionAction::KeepPlatform
                        && WebsiteDocumentHash::hash($document) !== $live['hash'],
                    'observed_core_hash' => $observedCoreHash,
                ],
                'created_at' => now(),
            ]);

            return $draft->fresh() ?? $draft;
        });
    }
}
