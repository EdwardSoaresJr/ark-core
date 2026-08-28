<?php

namespace App\Ark\Operations\Learn;

use App\Ark\Operations\Settings\ShopSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class LearnArkProgressController
{
    public function heartbeat(Request $request, LearnArkProgressRecorder $recorder): JsonResponse
    {
        $data = $request->validate([
            'article_key' => ['required', 'string', 'max:120'],
            'visible' => ['required', 'boolean'],
            'interacting' => ['required', 'boolean'],
        ]);

        try {
            $result = $recorder->heartbeat(
                $request->user(),
                $data['article_key'],
                (bool) $data['visible'],
                (bool) $data['interacting'],
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function checkpoint(Request $request, LearnArkProgressRecorder $recorder): JsonResponse
    {
        $data = $request->validate([
            'article_key' => ['required', 'string', 'max:120'],
            'checkpoint_key' => ['required', 'string', 'max:64'],
            'checkpoint_index' => ['required', 'integer', 'min:0', 'max:50'],
            'section_active_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
        ]);

        try {
            $result = $recorder->reachCheckpoint(
                $request->user(),
                $data['article_key'],
                $data['checkpoint_key'],
                (int) $data['checkpoint_index'],
                (int) $data['section_active_seconds'],
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function complete(Request $request, LearnArkProgressRecorder $recorder, LearnArkProgressResolver $resolver): JsonResponse
    {
        $data = $request->validate([
            'article_key' => ['required', 'string', 'max:120'],
            'checkpoint_keys' => ['required', 'array', 'min:1', 'max:50'],
            'checkpoint_keys.*' => ['required', 'string', 'max:64'],
        ]);

        try {
            $result = $recorder->complete(
                $request->user(),
                $data['article_key'],
                array_values($data['checkpoint_keys']),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            ...$result,
            'current' => $resolver->isCurrent($request->user()),
            'summary' => $resolver->summaryFor($request->user()),
        ]);
    }

    public function trainingGate(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isMasterAdmin()) {
            abort(403);
        }

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        ShopSettings::current()->update([
            'learn_training_gate_enabled' => (bool) $data['enabled'],
        ]);

        $message = $data['enabled']
            ? 'Required training gate is on — staff must finish guides or snooze to reach the workboard.'
            : 'Required training gate paused — staff can use the workboard without finishing guides.';

        return redirect()
            ->back()
            ->with('learn_gate_control', $message);
    }

    public function snooze(Request $request, LearnArkProgressResolver $resolver): RedirectResponse
    {
        $user = $request->user();

        if (! $resolver->canSnoozeTraining($user)) {
            $nextArticle = $resolver->nextRequiredArticle($user);

            if ($nextArticle !== null) {
                return redirect()
                    ->route('operations.learn.show', [
                        'role' => $nextArticle['section']->key,
                        'article' => $nextArticle['slug'],
                    ])
                    ->with('learn_snooze_blocked', 'Read at least one guide section before snoozing again.');
            }

            return redirect()
                ->route('operations.learn.index')
                ->with('learn_snooze_blocked', 'Read at least one guide section before snoozing again.');
        }

        $snoozedUntil = now()->addHours(LearnArkCurriculum::SNOOZE_HOURS);

        LearnTrainingSnooze::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'snoozed_at' => now(),
                'snoozed_until' => $snoozedUntil,
            ],
        );

        return redirect()
            ->route('operations.index')
            ->with('learn_snoozed', [
                'until_label' => $snoozedUntil->timezone(config('app.timezone'))->format('g:i A'),
                'hours' => LearnArkCurriculum::SNOOZE_HOURS,
            ]);
    }

    public function video(Request $request): JsonResponse
    {
        $data = $request->validate([
            'article_key' => ['required', 'string', 'max:120'],
            'video_key' => ['required', 'string', 'max:64'],
            'percent_watched' => ['required', 'integer', 'min:0', 'max:100'],
            'watched_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'last_position_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'completed' => ['required', 'boolean'],
        ]);

        $parsed = LearnArticleKey::parse($data['article_key']);

        [$roleKey, $slug] = $parsed ?? [null, null];

        if ($roleKey === null || LearnArkCatalog::articleFor($request->user(), $roleKey, $slug) === null) {
            return response()->json(['message' => 'Article not available for this user.'], 422);
        }

        $progress = LearnVideoProgress::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'article_key' => $data['article_key'],
                'video_key' => $data['video_key'],
            ],
            [
                'percent_watched' => max(
                    (int) $data['percent_watched'],
                    (int) (LearnVideoProgress::query()
                        ->where('user_id', $request->user()->id)
                        ->where('article_key', $data['article_key'])
                        ->where('video_key', $data['video_key'])
                        ->value('percent_watched') ?? 0),
                ),
                'watched_seconds' => (int) $data['watched_seconds'],
                'last_position_seconds' => (int) $data['last_position_seconds'],
                'completed' => (bool) $data['completed'],
            ],
        );

        return response()->json([
            'percent_watched' => (int) $progress->percent_watched,
            'completed' => (bool) $progress->completed,
        ]);
    }
}
