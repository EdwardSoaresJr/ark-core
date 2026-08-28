<?php

namespace App\Ark\Growth\Sessions;

use App\Ark\Growth\Contracts\Operations\PublicSurfaceActivityPayload;
use App\Ark\Growth\Content\ContentRegistry;
use App\Ark\Growth\Models\GrowthLastTouch;
use App\Ark\Growth\Models\GrowthSession;
use App\Ark\Growth\Models\GrowthTouchpoint;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Leads\Public\PublicSurfaceEventType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class GrowthSessionResolver
{
    public const VISITOR_COOKIE = 'ark_growth_visitor';

    public function resolveFromRequest(Request $request): ?GrowthSession
    {
        if (! $request->hasSession()) {
            return null;
        }

        return $this->resolveFromLaravelSession(
            (string) $request->session()->getId(),
            $this->visitorIdFromRequest($request),
            $request,
        );
    }

    public function resolveFromLaravelSession(
        string $laravelSessionId,
        ?string $visitorId = null,
        ?Request $request = null,
    ): ?GrowthSession {
        if ($laravelSessionId === '') {
            return null;
        }

        $existing = GrowthSession::query()
            ->where('laravel_session_id', $laravelSessionId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $visitorId = $visitorId ?? (string) Str::uuid();
        $meta = $request !== null ? $this->requestMeta($request) : [];

        return $this->findOrCreateGrowthSession($laravelSessionId, [
            'visitor_id' => $visitorId,
            'started_at' => now(),
            'first_landing_page' => $meta['landing_page'] ?? null,
            'first_referrer' => $meta['referrer'] ?? null,
            'first_search_query' => $meta['search_query'] ?? null,
            'first_campaign' => $meta['utm_campaign'] ?? null,
            'utm_source' => $meta['utm_source'] ?? null,
            'utm_medium' => $meta['utm_medium'] ?? null,
            'utm_campaign' => $meta['utm_campaign'] ?? null,
            'utm_term' => $meta['utm_term'] ?? null,
            'utm_content' => $meta['utm_content'] ?? null,
            'device' => $meta['device'] ?? null,
            'country' => $meta['country'] ?? null,
            'state' => $meta['state'] ?? null,
            'city' => $meta['city'] ?? null,
            'metadata' => $meta['metadata'] ?? null,
        ]);
    }

    public function recordTouchpoint(
        GrowthSession $session,
        GrowthTouchpointType $type,
        ?string $path = null,
        array $payload = [],
        ?Carbon $at = null,
        ?int $growthContentId = null,
    ): GrowthTouchpoint {
        if ($growthContentId === null && $path !== null) {
            $growthContentId = app(ContentRegistry::class)->findByPath($path)?->id;
        }

        if ($growthContentId !== null && $session->first_growth_content_id === null) {
            $session->forceFill(['first_growth_content_id' => $growthContentId])->save();
        }

        return GrowthTouchpoint::query()->create([
            'growth_session_id' => $session->id,
            'growth_content_id' => $growthContentId,
            'type' => $type,
            'path' => $path,
            'payload' => $payload !== [] ? $payload : null,
            'recorded_at' => $at ?? now(),
        ]);
    }

    public function applyLastTouch(GrowthSession $session, array $touch): void
    {
        GrowthLastTouch::query()->updateOrCreate(
            ['growth_session_id' => $session->id],
            [
                'landing_page' => $touch['landing_page'] ?? null,
                'referrer' => $touch['referrer'] ?? null,
                'campaign' => $touch['campaign'] ?? null,
                'search_query' => $touch['search_query'] ?? null,
                'utm_source' => $touch['utm_source'] ?? null,
                'utm_medium' => $touch['utm_medium'] ?? null,
                'utm_campaign' => $touch['utm_campaign'] ?? null,
                'utm_term' => $touch['utm_term'] ?? null,
                'utm_content' => $touch['utm_content'] ?? null,
                'touched_at' => now(),
            ],
        );
    }

    public function linkLead(GrowthSession $session, int $leadId): void
    {
        Lead::query()
            ->whereKey($leadId)
            ->whereNull('growth_session_id')
            ->update(['growth_session_id' => $session->id]);
    }

    public function linkConversation(GrowthSession $session, int $conversationId): void
    {
        Conversation::query()
            ->whereKey($conversationId)
            ->whereNull('growth_session_id')
            ->update(['growth_session_id' => $session->id]);
    }

    public function resolveFromActivity(\App\Ark\Growth\Contracts\Operations\PublicSurfaceActivityPayload $payload): ?GrowthSession
    {
        if ($payload->laravelSessionId === '') {
            return null;
        }

        $existing = GrowthSession::query()
            ->where('laravel_session_id', $payload->laravelSessionId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $touch = $this->touchFromPayload($payload);
        $meta = $payload->requestMeta;

        return $this->findOrCreateGrowthSession($payload->laravelSessionId, [
            'visitor_id' => $payload->visitorId ?? (string) Str::uuid(),
            'started_at' => now(),
            'first_landing_page' => $touch['landing_page'] ?? $meta['landing_page'] ?? null,
            'first_referrer' => $touch['referrer'] ?? $meta['referrer'] ?? null,
            'first_search_query' => $touch['search_query'] ?? $meta['search_query'] ?? null,
            'first_campaign' => $touch['campaign'] ?? $meta['utm_campaign'] ?? null,
            'utm_source' => $touch['utm_source'] ?? $meta['utm_source'] ?? null,
            'utm_medium' => $touch['utm_medium'] ?? $meta['utm_medium'] ?? null,
            'utm_campaign' => $touch['utm_campaign'] ?? $meta['utm_campaign'] ?? null,
            'utm_term' => $touch['utm_term'] ?? $meta['utm_term'] ?? null,
            'utm_content' => $touch['utm_content'] ?? $meta['utm_content'] ?? null,
            'device' => $meta['device'] ?? null,
            'country' => $meta['country'] ?? null,
            'state' => $meta['state'] ?? null,
            'city' => $meta['city'] ?? null,
            'metadata' => $meta['metadata'] ?? null,
        ]);
    }

    public function linkRepairOrder(GrowthSession $session, int $repairOrderId): void
    {
        RepairOrder::query()
            ->whereKey($repairOrderId)
            ->whereNull('growth_session_id')
            ->update(['growth_session_id' => $session->id]);
    }

  /**
     * @return array<string, mixed>
     */
    public function requestMeta(Request $request): array
    {
        $path = $this->pathFromPage($request->input('page'));
        $referrer = (string) $request->headers->get('referer', '');
        $utm = $this->utmFromRequest($request);

        return [
            'landing_page' => $path ?? $this->pathFromRequest($request),
            'referrer' => $referrer !== '' ? $referrer : null,
            'search_query' => $this->searchQueryFromReferrer($referrer) ?? $utm['term'] ?? null,
            'utm_source' => $utm['source'] ?? null,
            'utm_medium' => $utm['medium'] ?? null,
            'utm_campaign' => $utm['campaign'] ?? null,
            'utm_term' => $utm['term'] ?? null,
            'utm_content' => $utm['content'] ?? null,
            'device' => $this->deviceLabel((string) $request->userAgent()),
            'country' => $request->headers->get('CF-IPCountry'),
            'state' => $request->headers->get('CF-Region'),
            'city' => $request->headers->get('CF-IPCity'),
            'metadata' => [
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function touchFromPayload(PublicSurfaceActivityPayload $payload): array
    {
        $path = $this->pathFromContext($payload->context);
        $meta = $payload->requestMeta;

        return [
            'landing_page' => $path ?? $meta['landing_page'] ?? null,
            'referrer' => $meta['referrer'] ?? null,
            'campaign' => $meta['utm_campaign'] ?? null,
            'search_query' => $meta['search_query'] ?? null,
            'utm_source' => $meta['utm_source'] ?? null,
            'utm_medium' => $meta['utm_medium'] ?? null,
            'utm_campaign' => $meta['utm_campaign'] ?? null,
            'utm_term' => $meta['utm_term'] ?? null,
            'utm_content' => $meta['utm_content'] ?? null,
        ];
    }

    public function visitorIdFromRequest(Request $request): string
    {
        $cookie = (string) $request->cookie(self::VISITOR_COOKIE, '');

        if ($cookie !== '' && Str::isUuid($cookie)) {
            return $cookie;
        }

        return (string) Str::uuid();
    }

    public function mapSurfaceEventType(string $surfaceType): ?GrowthTouchpointType
    {
        $enum = PublicSurfaceEventType::tryFrom($surfaceType);

        if ($enum === null) {
            return null;
        }

        return match ($enum) {
            PublicSurfaceEventType::SurfaceViewed => GrowthTouchpointType::PageViewed,
            PublicSurfaceEventType::FormExposed => GrowthTouchpointType::FormExposed,
            PublicSurfaceEventType::LeadStarted => GrowthTouchpointType::FormStarted,
            PublicSurfaceEventType::LeadSubmitted => GrowthTouchpointType::LeadSubmitted,
            PublicSurfaceEventType::LeadCreated => GrowthTouchpointType::LeadCreated,
            PublicSurfaceEventType::CallClicked => GrowthTouchpointType::CallClicked,
            PublicSurfaceEventType::TextClicked => GrowthTouchpointType::TextClicked,
            PublicSurfaceEventType::CommonProblemLinkClicked => GrowthTouchpointType::CommonProblemLinkClicked,
        };
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    private function pathFromContext(?array $context): ?string
    {
        if ($context === null) {
            return null;
        }

        $page = trim((string) ($context['page'] ?? ''));

        return $this->pathFromPage($page);
    }

    private function pathFromPage(?string $page): ?string
    {
        if ($page === null || trim($page) === '') {
            return null;
        }

        $page = trim($page);

        if (str_starts_with($page, '/')) {
            return $page;
        }

        if (str_contains($page, 'common-problems.')) {
            $slug = str_replace('common-problems.', '', $page);

            return '/common-problems/'.$slug;
        }

        if ($page === 'homepage' || $page === 'home') {
            return '/';
        }

        return '/'.$page;
    }

    private function pathFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '/';
        }

        return $path;
    }

    public function pathFromRequest(Request $request): string
    {
        $path = '/'.trim($request->path(), '/');

        return $path === '//' ? '/' : $path;
    }

    /**
     * @return array<string, string|null>
     */
    private function utmFromRequest(Request $request): array
    {
        return [
            'source' => $request->query('utm_source'),
            'medium' => $request->query('utm_medium'),
            'campaign' => $request->query('utm_campaign'),
            'term' => $request->query('utm_term'),
            'content' => $request->query('utm_content'),
        ];
    }

    private function searchQueryFromReferrer(string $referrer): ?string
    {
        if ($referrer === '') {
            return null;
        }

        $parts = parse_url($referrer);

        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! str_contains($host, 'google.') && ! str_contains($host, 'bing.')) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        $term = trim((string) ($query['q'] ?? $query['p'] ?? ''));

        return $term !== '' ? $term : null;
    }

    private function deviceLabel(string $userAgent): string
    {
        $ua = strtolower($userAgent);

        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'mobile';
        }

        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function findOrCreateGrowthSession(string $laravelSessionId, array $attributes): GrowthSession
    {
        $existing = GrowthSession::query()
            ->where('laravel_session_id', $laravelSessionId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return GrowthSession::query()->create(array_merge($attributes, [
                'laravel_session_id' => $laravelSessionId,
            ]));
        } catch (UniqueConstraintViolationException) {
            return GrowthSession::query()
                ->where('laravel_session_id', $laravelSessionId)
                ->firstOrFail();
        }
    }
}
