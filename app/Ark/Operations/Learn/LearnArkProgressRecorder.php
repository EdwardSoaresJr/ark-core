<?php

namespace App\Ark\Operations\Learn;

use App\Models\User;
use Illuminate\Support\Carbon;
use RuntimeException;

class LearnArkProgressRecorder
{
    public function __construct(
        private readonly LearnArkProgressResolver $resolver,
    ) {}

    /**
     * @return array{active_seconds: int, idle: bool}
     */
    public function heartbeat(User $user, string $articleKey, bool $visible, bool $interacting): array
    {
        $this->assertArticleAccessible($user, $articleKey);

        $completion = LearnCompletion::query()
            ->where('user_id', $user->id)
            ->where('article_key', $articleKey)
            ->first();

        if (LearnArkCurriculum::completionIsCurrent($completion, $articleKey)) {
            return [
                'active_seconds' => (int) ($completion?->active_seconds ?? 0),
                'idle' => false,
            ];
        }

        $session = LearnSession::query()->firstOrCreate(
            ['user_id' => $user->id, 'article_key' => $articleKey],
            ['active_seconds' => 0],
        );

        $now = now();
        $idle = $this->isIdle($session, $now);

        if ($visible && $interacting && ! $idle) {
            $chunk = $this->heartbeatChunkSeconds($session, $now);

            if ($chunk > 0 && $session->active_seconds < LearnArkCurriculum::SESSION_ACTIVE_CAP_SECONDS) {
                $session->active_seconds = min(
                    LearnArkCurriculum::SESSION_ACTIVE_CAP_SECONDS,
                    $session->active_seconds + $chunk,
                );
            }
        }

        if ($interacting) {
            $session->last_interaction_at = $now;
        }

        $session->last_heartbeat_at = $now;
        $session->save();

        return [
            'active_seconds' => (int) $session->active_seconds,
            'idle' => $idle && ! $interacting,
        ];
    }

    /**
     * @return array{checkpoint_key: string, checkpoint_index: int, active_seconds: int}
     */
    public function reachCheckpoint(
        User $user,
        string $articleKey,
        string $checkpointKey,
        int $checkpointIndex,
        int $sectionActiveSeconds,
    ): array {
        $this->assertArticleAccessible($user, $articleKey);

        $completion = LearnCompletion::query()
            ->where('user_id', $user->id)
            ->where('article_key', $articleKey)
            ->first();

        if (LearnArkCurriculum::completionIsCurrent($completion, $articleKey)) {
            throw new RuntimeException('Article already completed.');
        }

        if ($sectionActiveSeconds < LearnArkCurriculum::CHECKPOINT_MIN_ACTIVE_SECONDS) {
            throw new RuntimeException('Not enough active time in this section.');
        }

        $session = LearnSession::query()->firstOrCreate(
            ['user_id' => $user->id, 'article_key' => $articleKey],
            ['active_seconds' => 0],
        );

        $lastCheckpoint = LearnCheckpoint::query()
            ->where('user_id', $user->id)
            ->where('article_key', $articleKey)
            ->orderByDesc('checkpoint_index')
            ->first();

        if ($lastCheckpoint !== null) {
            if ($checkpointIndex !== $lastCheckpoint->checkpoint_index + 1) {
                throw new RuntimeException('Checkpoints must be reached in order.');
            }

            $secondsSinceLast = $lastCheckpoint->reached_at
                ? $lastCheckpoint->reached_at->diffInSeconds(now())
                : 0;

            if ($secondsSinceLast < LearnArkCurriculum::MIN_SECONDS_BETWEEN_CHECKPOINTS) {
                throw new RuntimeException('Checkpoint reached too quickly.');
            }
        } elseif ($checkpointIndex !== 0) {
            throw new RuntimeException('First checkpoint must be index zero.');
        }

        if (LearnCheckpoint::query()
            ->where('user_id', $user->id)
            ->where('article_key', $articleKey)
            ->where('checkpoint_key', $checkpointKey)
            ->exists()) {
            return [
                'checkpoint_key' => $checkpointKey,
                'checkpoint_index' => $checkpointIndex,
                'active_seconds' => (int) $session->active_seconds,
            ];
        }

        LearnCheckpoint::query()->create([
            'user_id' => $user->id,
            'article_key' => $articleKey,
            'checkpoint_key' => $checkpointKey,
            'checkpoint_index' => $checkpointIndex,
            'active_seconds_at_reach' => (int) $session->active_seconds,
            'reached_at' => now(),
        ]);

        return [
            'checkpoint_key' => $checkpointKey,
            'checkpoint_index' => $checkpointIndex,
            'active_seconds' => (int) $session->active_seconds,
        ];
    }

    /**
     * @param  list<string>  $checkpointKeys
     * @return array{completed_at: string, active_seconds: int}
     */
    public function complete(User $user, string $articleKey, array $checkpointKeys): array
    {
        $this->assertArticleAccessible($user, $articleKey);

        $existing = LearnCompletion::query()
            ->where('user_id', $user->id)
            ->where('article_key', $articleKey)
            ->first();

        if (LearnArkCurriculum::completionIsCurrent($existing, $articleKey)) {
            return [
                'completed_at' => $existing->completed_at?->toIso8601String() ?? now()->toIso8601String(),
                'active_seconds' => (int) $existing->active_seconds,
            ];
        }

        if ($existing !== null) {
            $existing->delete();
            LearnCheckpoint::query()
                ->where('user_id', $user->id)
                ->where('article_key', $articleKey)
                ->delete();
            LearnSession::query()
                ->where('user_id', $user->id)
                ->where('article_key', $articleKey)
                ->delete();
        }

        $session = LearnSession::query()
            ->where('user_id', $user->id)
            ->where('article_key', $articleKey)
            ->first();

        $activeSeconds = (int) ($session?->active_seconds ?? 0);
        $minActive = LearnArkCurriculum::minActiveSeconds($articleKey);

        if ($activeSeconds < $minActive) {
            throw new RuntimeException('Not enough active reading time.');
        }

        $expectedKeys = array_values($checkpointKeys);

        if ($expectedKeys === []) {
            throw new RuntimeException('No checkpoints recorded for this article.');
        }

        $reached = LearnCheckpoint::query()
            ->where('user_id', $user->id)
            ->where('article_key', $articleKey)
            ->orderBy('checkpoint_index')
            ->pluck('checkpoint_key')
            ->values()
            ->all();

        if ($reached !== $expectedKeys) {
            throw new RuntimeException('All section checkpoints must be completed.');
        }

        $completion = LearnCompletion::query()->create([
            'user_id' => $user->id,
            'article_key' => $articleKey,
            'catalog_version' => LearnArkCurriculum::VERSION,
            'article_version' => LearnArkCurriculum::articleContentVersion($articleKey),
            'active_seconds' => $activeSeconds,
            'completed_at' => now(),
        ]);

        return [
            'completed_at' => $completion->completed_at?->toIso8601String() ?? now()->toIso8601String(),
            'active_seconds' => $activeSeconds,
        ];
    }

    private function assertArticleAccessible(User $user, string $articleKey): void
    {
        $parsed = LearnArticleKey::parse($articleKey);

        if ($parsed === null) {
            throw new RuntimeException('Invalid article key.');
        }

        [$roleKey, $slug] = $parsed;

        if (LearnArkCatalog::articleFor($user, $roleKey, $slug) === null) {
            throw new RuntimeException('Article not available for this user.');
        }
    }

    private function isIdle(LearnSession $session, Carbon $now): bool
    {
        if ($session->last_interaction_at === null) {
            return false;
        }

        return $session->last_interaction_at->diffInSeconds($now) > LearnArkCurriculum::IDLE_SECONDS;
    }

    private function heartbeatChunkSeconds(LearnSession $session, Carbon $now): int
    {
        if ($session->last_heartbeat_at === null) {
            return 0;
        }

        $elapsed = $session->last_heartbeat_at->diffInSeconds($now);

        if ($elapsed <= 0 || $elapsed > LearnArkCurriculum::HEARTBEAT_ACTIVE_CHUNK_SECONDS + 5) {
            return 0;
        }

        return min($elapsed, LearnArkCurriculum::HEARTBEAT_ACTIVE_CHUNK_SECONDS);
    }
}
