<?php

namespace App\Ark\Operations\Learn;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LearnArkProgressResolver
{
    /**
     * @return Collection<int, array{article_key: string, title: string, section_label: string, completed: bool, stale: bool, completed_at: ?string, active_seconds: int}>
     */
    public function requiredProgressFor(User $user): Collection
    {
        $completions = LearnCompletion::query()
            ->where('user_id', $user->id)
            ->whereIn('article_key', LearnArkCurriculum::requiredArticleKeys())
            ->get()
            ->keyBy('article_key');

        return LearnArkCurriculum::requiredArticlesFor($user)
            ->map(function (array $article) use ($completions): array {
                $articleKey = $article['article_key'];
                $completion = $completions->get($articleKey);
                $isCurrent = LearnArkCurriculum::completionIsCurrent($completion, $articleKey);

                return [
                    'article_key' => $articleKey,
                    'role' => $article['section']->key,
                    'slug' => $article['slug'],
                    'title' => $article['title'],
                    'section_label' => $article['section']->label,
                    'min_active_seconds' => $article['min_active_seconds'],
                    'completed' => $isCurrent,
                    'stale' => $completion !== null && ! $isCurrent,
                    'completed_at' => $isCurrent ? $completion?->completed_at?->toIso8601String() : null,
                    'active_seconds' => (int) ($completion?->active_seconds ?? 0),
                ];
            });
    }

    public function isCurrent(User $user): bool
    {
        return $this->shellProjectionFor($user)->isCurrent;
    }

    public function isGateOpen(User $user): bool
    {
        if (! LearnArkTrainingGate::isActiveFor($user)) {
            return true;
        }

        return $this->shellProjectionFor($user)->isGateOpen();
    }

    public function shellProjectionFor(User $user): LearnTrainingShellProjection
    {
        $request = request();
        $cacheKey = 'operations.learn_training_shell';

        if ($request !== null && $request->attributes->has($cacheKey)) {
            /** @var LearnTrainingShellProjection $projection */
            $projection = $request->attributes->get($cacheKey);

            return $projection;
        }

        $projection = $this->buildShellProjectionFor($user);
        $request?->attributes->set($cacheKey, $projection);

        return $projection;
    }

    private function buildShellProjectionFor(User $user): LearnTrainingShellProjection
    {
        if (! LearnArkCurriculum::appliesTo($user)) {
            return new LearnTrainingShellProjection(
                isCurrent: true,
                snoozeState: null,
                canSnoozeTraining: false,
            );
        }

        $required = LearnArkCurriculum::requiredArticlesFor($user);

        if ($required->isEmpty()) {
            return new LearnTrainingShellProjection(
                isCurrent: true,
                snoozeState: null,
                canSnoozeTraining: false,
            );
        }

        $completions = $this->requiredCompletionsFor($user, $required);
        $isCurrent = $this->requiredArticlesAreCurrent($required, $completions);

        if ($isCurrent) {
            return new LearnTrainingShellProjection(
                isCurrent: true,
                snoozeState: null,
                canSnoozeTraining: false,
            );
        }

        $snoozeState = $this->snoozeState($user);

        return new LearnTrainingShellProjection(
            isCurrent: false,
            snoozeState: $snoozeState,
            canSnoozeTraining: $this->canSnoozeTrainingFor($user),
        );
    }

    public function activeSnooze(User $user): ?LearnTrainingSnooze
    {
        $snooze = LearnTrainingSnooze::query()
            ->where('user_id', $user->id)
            ->first();

        if ($snooze === null || ! $snooze->isActive()) {
            return null;
        }

        return $snooze;
    }

    public function canSnoozeTraining(User $user): bool
    {
        return $this->shellProjectionFor($user)->canSnoozeTraining;
    }

    private function canSnoozeTrainingFor(User $user): bool
    {
        if (! LearnArkTrainingGate::isActiveFor($user)) {
            return false;
        }

        $lastSnooze = LearnTrainingSnooze::query()
            ->where('user_id', $user->id)
            ->first();

        if ($lastSnooze === null) {
            return true;
        }

        if ($lastSnooze->isActive()) {
            return $this->hasTrainingProgressSince($user, $lastSnooze->snoozed_at);
        }

        return $this->hasTrainingProgressSince($user, $lastSnooze->snoozed_until);
    }

    public function hasTrainingProgressSince(User $user, Carbon $since): bool
    {
        if (LearnCompletion::query()
            ->where('user_id', $user->id)
            ->where('completed_at', '>', $since)
            ->exists()) {
            return true;
        }

        if (LearnCheckpoint::query()
            ->where('user_id', $user->id)
            ->where('reached_at', '>', $since)
            ->exists()) {
            return true;
        }

        return LearnSession::query()
            ->where('user_id', $user->id)
            ->where('active_seconds', '>', 0)
            ->where(function ($query) use ($since): void {
                $query->where('updated_at', '>', $since)
                    ->orWhere('last_interaction_at', '>', $since);
            })
            ->exists();
    }

    /**
     * @return array{active: bool, snoozed_at: string, snoozed_until: string, snoozed_until_label: string, hours: int}|null
     */
    public function snoozeState(User $user): ?array
    {
        $snooze = $this->activeSnooze($user);

        if ($snooze === null) {
            return null;
        }

        return [
            'active' => true,
            'snoozed_at' => $snooze->snoozed_at->toIso8601String(),
            'snoozed_until' => $snooze->snoozed_until->toIso8601String(),
            'snoozed_until_label' => $snooze->snoozed_until->timezone(config('app.timezone'))->format('g:i A'),
            'hours' => LearnArkCurriculum::SNOOZE_HOURS,
        ];
    }

    public function nextRequiredArticle(User $user): ?array
    {
        $completions = LearnCompletion::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('article_key');

        return LearnArkCurriculum::requiredArticlesFor($user)
            ->first(function (array $article) use ($completions): bool {
                $articleKey = $article['article_key'];

                return ! LearnArkCurriculum::completionIsCurrent($completions->get($articleKey), $articleKey);
            });
    }

    /**
     * @return array{completed: int, required: int, percent: int, stale: int}
     */
    public function summaryFor(User $user): array
    {
        $required = LearnArkCurriculum::requiredArticlesFor($user);
        $requiredCount = $required->count();

        if ($requiredCount === 0) {
            return ['completed' => 0, 'required' => 0, 'percent' => 100, 'stale' => 0];
        }

        $completions = LearnCompletion::query()
            ->where('user_id', $user->id)
            ->whereIn('article_key', $required->pluck('article_key'))
            ->get()
            ->keyBy('article_key');

        $completed = 0;
        $stale = 0;

        foreach ($required as $article) {
            $articleKey = $article['article_key'];
            $completion = $completions->get($articleKey);

            if (LearnArkCurriculum::completionIsCurrent($completion, $articleKey)) {
                $completed++;
            } elseif ($completion !== null) {
                $stale++;
            }
        }

        return [
            'completed' => $completed,
            'required' => $requiredCount,
            'percent' => (int) floor(($completed / $requiredCount) * 100),
            'stale' => $stale,
        ];
    }

    public function articleState(User $user, string $articleKey): array
    {
        $completion = LearnCompletion::query()
            ->where('user_id', $user->id)
            ->where('article_key', $articleKey)
            ->first();

        $isCurrent = LearnArkCurriculum::completionIsCurrent($completion, $articleKey);

        $session = LearnSession::query()
            ->where('user_id', $user->id)
            ->where('article_key', $articleKey)
            ->first();

        $checkpoints = LearnCheckpoint::query()
            ->where('user_id', $user->id)
            ->where('article_key', $articleKey)
            ->orderBy('checkpoint_index')
            ->get()
            ->map(fn (LearnCheckpoint $checkpoint): array => [
                'key' => $checkpoint->checkpoint_key,
                'index' => $checkpoint->checkpoint_index,
                'reached_at' => $checkpoint->reached_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'article_key' => $articleKey,
            'required' => LearnArkCurriculum::isRequired($articleKey),
            'min_active_seconds' => LearnArkCurriculum::minActiveSeconds($articleKey),
            'completed' => $isCurrent,
            'content_stale' => $completion !== null && ! $isCurrent,
            'article_version' => (int) ($completion?->article_version ?? 0),
            'required_version' => LearnArkCurriculum::articleContentVersion($articleKey),
            'completed_at' => $isCurrent ? $completion?->completed_at?->toIso8601String() : null,
            'active_seconds' => (int) ($session?->active_seconds ?? $completion?->active_seconds ?? 0),
            'checkpoints' => $checkpoints,
            'checkpoint_min_active_seconds' => LearnArkCurriculum::CHECKPOINT_MIN_ACTIVE_SECONDS,
            'idle_seconds' => LearnArkCurriculum::IDLE_SECONDS,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $required
     * @return Collection<string, LearnCompletion>
     */
    private function requiredCompletionsFor(User $user, Collection $required): Collection
    {
        return LearnCompletion::query()
            ->where('user_id', $user->id)
            ->whereIn('article_key', $required->pluck('article_key'))
            ->get()
            ->keyBy('article_key');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $required
     * @param  Collection<string, LearnCompletion>  $completions
     */
    private function requiredArticlesAreCurrent(Collection $required, Collection $completions): bool
    {
        $currentCount = $required
            ->filter(fn (array $article): bool => LearnArkCurriculum::completionIsCurrent(
                $completions->get($article['article_key']),
                $article['article_key'],
            ))
            ->count();

        return $currentCount >= $required->count();
    }
}
