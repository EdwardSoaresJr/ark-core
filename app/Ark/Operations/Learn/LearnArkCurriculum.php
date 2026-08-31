<?php

namespace App\Ark\Operations\Learn;

use App\Models\User;
use Illuminate\Support\Collection;

final class LearnArkCurriculum
{
    public const VERSION = 3;

    public const IDLE_SECONDS = 120;

    public const CHECKPOINT_MIN_ACTIVE_SECONDS = 20;

    public const MIN_SECONDS_BETWEEN_CHECKPOINTS = 15;

    public const HEARTBEAT_ACTIVE_CHUNK_SECONDS = 15;

    public const SESSION_ACTIVE_CAP_SECONDS = 1500;

    public const DEFAULT_MIN_ACTIVE_SECONDS = 90;

    public const SNOOZE_HOURS = 4;

    /**
     * @return list<string>
     */
    public static function requiredArticleKeys(): array
    {
        return [
            'advisor:getting-started',
            'advisor:workboard-lanes',
            'advisor:workspace-tabs',
            'advisor:advisor-intake',
            'advisor:customer-hub',
            'advisor:comms-queue',
            'advisor:texting-customers',
            'advisor:scopes-and-intent',
            'advisor:repair-actions',
            'advisor:parts-and-labor',
            'advisor:customer-authorization',
            'advisor:lifecycle-transitions',
            'technician:getting-started',
            'technician:reading-estimates',
            'technician:ro-status',
            'technician:writing-findings',
            'admin:getting-started',
            'admin:roles-and-access',
        ];
    }

    /**
     * Next optional guides to promote to required — prioritized by floor impact.
     *
     * @return list<string>
     */
    public static function nextWaveArticleKeys(): array
    {
        return [
            'advisor:remote-sell',
            'advisor:incoming-calls-floor',
            'advisor:ark-mobile-attention',
            'advisor:ark-mobile-check-in',
            'advisor:deposits-and-invoicing',
            'technician:multi-point-inspection',
            'technician:ark-mobile-field-work',
            'technician:ark-mobile-concern-workspace',
            'admin:staff-onboarding',
            'admin:ark-mobile-push-setup',
            'admin:ark-mobile-android-deploy',
        ];
    }

    /**
     * Per-article content generation — bump only the keys whose guides changed materially.
     * Unlisted articles stay at version 1 so staff are not forced to re-read the whole catalog.
     *
     * @return array<string, int>
     */
    public static function articleContentVersions(): array
    {
        return [
            'advisor:customer-authorization' => 3,
            'advisor:texting-customers' => 4,
            'advisor:scopes-and-intent' => 4,
            'advisor:lifecycle-transitions' => 4,
            'advisor:getting-started' => 2,
            'advisor:customer-hub' => 2,
            'advisor:comms-queue' => 3,
            'advisor:customer-search' => 2,
            'advisor:advisor-intake' => 2,
            'advisor:incoming-calls-floor' => 3,
            'advisor:ark-mobile-attention' => 2,
            'advisor:ark-mobile-check-in' => 1,
            'technician:ark-mobile-field-work' => 2,
            'technician:ark-mobile-concern-workspace' => 1,
            'advisor:note-privacy' => 4,
            'advisor:estimate-review-mode' => 4,
            'admin:email-delivery' => 3,
            'admin:comms-health-check' => 5,
            'admin:ark-mobile-push-setup' => 2,
            'admin:ark-mobile-android-deploy' => 1,
            'admin:telephony-sip-setup' => 3,
            'owner:communications-setup' => 4,
            'advisor:deposits-and-invoicing' => 3,
            'advisor:portal-payment-links' => 3,
            'advisor:ro-printing' => 3,
        ];
    }

    public static function articleContentVersion(string $articleKey): int
    {
        return self::articleContentVersions()[$articleKey] ?? 1;
    }

    public static function completionIsCurrent(?LearnCompletion $completion, string $articleKey): bool
    {
        if ($completion === null) {
            return false;
        }

        $completedVersion = (int) ($completion->article_version ?? 1);

        return $completedVersion >= self::articleContentVersion($articleKey);
    }

    public static function isRequired(string $articleKey): bool
    {
        return in_array($articleKey, self::requiredArticleKeys(), true);
    }

    public static function minActiveSeconds(string $articleKey): int
    {
        return match ($articleKey) {
            'advisor:getting-started' => 90,
            'advisor:workboard-lanes' => 90,
            'advisor:workspace-tabs' => 90,
            'advisor:advisor-intake' => 120,
            'advisor:customer-hub' => 120,
            'advisor:comms-queue' => 90,
            'advisor:texting-customers' => 120,
            'advisor:scopes-and-intent' => 120,
            'advisor:repair-actions' => 120,
            'advisor:parts-and-labor' => 120,
            'advisor:customer-authorization' => 90,
            'advisor:lifecycle-transitions' => 120,
            'technician:getting-started' => 90,
            'technician:reading-estimates' => 90,
            'technician:ro-status' => 90,
            'technician:writing-findings' => 90,
            'admin:getting-started' => 90,
            'admin:roles-and-access' => 120,
            default => self::DEFAULT_MIN_ACTIVE_SECONDS,
        };
    }

    /**
     * @return Collection<int, array{article_key: string, role: string, slug: string, title: string, summary: string, section_label: string}>
     */
    public static function nextWaveArticlesFor(User $user): Collection
    {
        return collect(self::nextWaveArticleKeys())
            ->map(function (string $articleKey) use ($user): ?array {
                $parsed = LearnArticleKey::parse($articleKey);

                if ($parsed === null) {
                    return null;
                }

                [$roleKey, $slug] = $parsed;
                $article = LearnArkCatalog::articleFor($user, $roleKey, $slug);

                if ($article === null) {
                    return null;
                }

                return [
                    'article_key' => $articleKey,
                    'role' => $roleKey,
                    'slug' => $slug,
                    'title' => $article['title'],
                    'summary' => $article['summary'],
                    'section_label' => $article['section']->label,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{article_key: string, section: LearnArkSection, slug: string, title: string, summary: string, view: string, required: bool, min_active_seconds: int}>
     */
    public static function requiredArticlesFor(User $user): Collection
    {
        return LearnArkCatalog::visibleArticlesFor($user)
            ->filter(fn (array $article): bool => self::isRequired(LearnArticleKey::make($article['section']->key, $article['slug'])))
            ->map(function (array $article): array {
                $articleKey = LearnArticleKey::make($article['section']->key, $article['slug']);

                return [
                    ...$article,
                    'article_key' => $articleKey,
                    'required' => true,
                    'min_active_seconds' => self::minActiveSeconds($articleKey),
                ];
            })
            ->values();
    }

    public static function appliesTo(User $user): bool
    {
        return LearnArkSection::highestStaffRole($user) !== null
            && self::requiredArticlesFor($user)->isNotEmpty();
    }
}
