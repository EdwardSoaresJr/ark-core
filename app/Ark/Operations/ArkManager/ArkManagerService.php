<?php

namespace App\Ark\Operations\ArkManager;

use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Today\AdvisorTodayProjection;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class ArkManagerService
{
    private const CACHE_MINUTES = 30;

    /** Bump when brief cache payload shape changes to bust stale Redis entries. */
    private const CACHE_VERSION = 2;

    public function __construct(
        private readonly ArkManagerContextBuilder $contextBuilder,
        private readonly ArkManagerProviderResolver $providerResolver,
    ) {}

    public function morningBrief(AdvisorTodayProjection $today, ?User $actor = null): ArkManagerMorningBrief
    {
        $context = $this->contextBuilder->fromToday($today);
        $recommendedFocus = $this->contextBuilder->recommendedFocus($context);
        $cacheKey = $this->cacheKey('brief', $context->shopDate, $actor?->id);

        $cached = Cache::get($cacheKey);
        $brief = $this->briefFromCache($cached);

        if ($brief !== null) {
            return $brief;
        }

        $brief = $this->providerResolver
            ->resolve()
            ->morningBrief($context, $recommendedFocus);

        Cache::put($cacheKey, $brief->toCachePayload(), now()->addMinutes(self::CACHE_MINUTES));

        return $brief;
    }

    public function explainRecommendation(AdvisorTodayProjection $today, array $recommendation): ArkManagerRecommendationExplanation
    {
        $context = $this->contextBuilder->fromToday($today);

        return $this->providerResolver
            ->resolve()
            ->explainRecommendation($context, $recommendation);
    }

    /**
     * @param  array<string, mixed>  $draftContext
     */
    public function draftCommunication(AdvisorTodayProjection $today, array $draftContext): ArkManagerCommunicationDraft
    {
        $context = $this->contextBuilder->fromToday($today);

        return $this->providerResolver
            ->resolve()
            ->draftCommunication($context, $draftContext);
    }

    private function briefFromCache(mixed $cached): ?ArkManagerMorningBrief
    {
        if ($cached instanceof ArkManagerMorningBrief) {
            return $cached;
        }

        if (! is_array($cached)) {
            return null;
        }

        if (! isset($cached['paragraphs'], $cached['recommendedFocus'], $cached['source'], $cached['aiEnhanced'])) {
            return null;
        }

        if (! is_array($cached['paragraphs']) || ! is_string($cached['recommendedFocus']) || ! is_string($cached['source'])) {
            return null;
        }

        return ArkManagerMorningBrief::fromCachePayload($cached);
    }

    private function cacheKey(string $kind, string $shopDate, ?int $userId): string
    {
        $date = $shopDate !== '' ? $shopDate : ShopDisplayTimezone::now()->toDateString();

        return 'ark-manager:v'.self::CACHE_VERSION.':'.$kind.':'.$date.':'.($userId ?? 'guest');
    }
}
