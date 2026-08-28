<?php

namespace App\Ark\Growth\Sessions;

use App\Ark\Growth\Content\ContentRegistry;
use App\Ark\Growth\Models\GrowthSession;
use App\Ark\Growth\Models\GrowthTouchpoint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Server-side public surface intelligence — Growth-owned, never blocks the response.
 */
final class PublicGrowthSurfaceRecorder
{
    public function __construct(
        private readonly GrowthSessionResolver $sessions,
        private readonly ContentRegistry $content,
    ) {}

    public function recordPageView(Request $request): void
    {
        if (! config('growth.enabled', true) || ! config('growth.public_surface.server_page_views', true)) {
            return;
        }

        if (! $request->isMethod('GET') || ! $request->hasSession()) {
            return;
        }

        if ($this->shouldSkipPath($request->path())) {
            return;
        }

        $session = $this->sessions->resolveFromRequest($request);

        if ($session === null) {
            return;
        }

        $path = $this->sessions->pathFromRequest($request);

        if ($this->hasRecentPageView($session, $path)) {
            $this->sessions->applyLastTouch($session, $this->sessions->requestMeta($request));

            return;
        }

        $contentId = $this->content->findByPath($path)?->id;
        $this->stampFirstContent($session, $contentId);

        $this->sessions->recordTouchpoint(
            $session,
            GrowthTouchpointType::PageViewed,
            $path,
            ['source' => 'server'],
            growthContentId: $contentId,
        );

        $this->sessions->applyLastTouch($session, $this->sessions->requestMeta($request));
    }

    private function shouldSkipPath(string $path): bool
    {
        return in_array($path, [
            'robots.txt',
            'sitemap.xml',
            'up',
            'growth/events',
        ], true) || str_starts_with($path, 'app/');
    }

    private function hasRecentPageView(GrowthSession $session, string $path): bool
    {
        return GrowthTouchpoint::query()
            ->where('growth_session_id', $session->id)
            ->where('type', GrowthTouchpointType::PageViewed)
            ->where('path', $path)
            ->where('recorded_at', '>=', Carbon::now()->subMinutes(30))
            ->exists();
    }

    private function stampFirstContent(GrowthSession $session, ?int $contentId): void
    {
        if ($contentId === null || $session->first_growth_content_id !== null) {
            return;
        }

        $session->forceFill(['first_growth_content_id' => $contentId])->save();
    }
}
