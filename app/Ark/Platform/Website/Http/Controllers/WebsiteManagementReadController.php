<?php

namespace App\Ark\Platform\Website\Http\Controllers;

use App\Ark\Platform\Website\WebsiteDraftRevision;
use App\Ark\Platform\Website\WebsiteDraftRevisionAction;
use App\Ark\Platform\Website\WebsitePublication;
use App\Ark\Platform\Website\WebsiteSite;
use Illuminate\Http\JsonResponse;

final class WebsiteManagementReadController
{
    public function __invoke(string $publicHost): JsonResponse
    {
        $host = strtolower(trim($publicHost));
        if ($host === '' || str_contains($host, '..')) {
            abort(404);
        }

        $sites = WebsiteSite::query()
            ->whereRaw('lower(public_host) = ?', [$host])
            ->limit(2)
            ->get();

        if ($sites->count() !== 1) {
            abort($sites->count() > 1 ? 409 : 404);
        }

        $site = $sites->first();
        $draft = $site->draft;
        if ($draft === null) {
            abort(404);
        }

        $publications = WebsitePublication::query()
            ->where('website_site_id', $site->id)
            ->where('is_current', true)
            ->limit(2)
            ->get();

        if ($publications->count() > 1) {
            abort(409);
        }

        $publication = $publications->first();
        $revisions = WebsiteDraftRevision::query()
            ->where('website_draft_id', $draft->id)
            ->orderBy('revision')
            ->get();

        return response()->json([
            'public_host' => $host,
            'writing_authority' => $site->writing_authority->value,
            'draft' => [
                'revision' => (int) $draft->revision,
                'document' => is_array($draft->document) ? $draft->document : [],
            ],
            'publication' => $publication instanceof WebsitePublication ? [
                'version' => (int) $publication->version,
                'content_hash' => $publication->content_hash,
                'published_at' => $publication->published_at?->toIso8601String(),
            ] : null,
            'revisions' => $revisions->map(function (WebsiteDraftRevision $revision): array {
                $action = $revision->action;

                return [
                    'revision' => (int) $revision->revision,
                    'action' => $action instanceof WebsiteDraftRevisionAction ? $action->value : (string) $action,
                    'created_at' => $revision->created_at?->toIso8601String(),
                ];
            })->values()->all(),
        ]);
    }
}
