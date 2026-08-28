<?php

namespace App\Http\Middleware;

use App\Ark\Operations\Learn\ArkademyUrls;
use App\Ark\Operations\Learn\LearnArkCurriculum;
use App\Ark\Operations\Learn\LearnArkProgressResolver;
use App\Ark\Operations\Learn\LearnArkTrainingGate;
use App\Models\User;
use App\Support\Branding\Branding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLearnArkTrainingCurrent
{
    public function __construct(
        private readonly LearnArkProgressResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! LearnArkTrainingGate::isActiveFor($user)) {
            return $next($request);
        }

        if ($request->routeIs(
            'operations.learn.*',
            'profile.*',
            'logout',
            'oidc.authorize',
        )) {
            return $next($request);
        }

        if ($this->resolver->isGateOpen($user)) {
            return $next($request);
        }

        $nextArticle = $this->resolver->nextRequiredArticle($user);
        $summary = $this->resolver->summaryFor($user);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Complete required '.Branding::learnName().' guides before continuing.',
                'learn_required' => true,
                'summary' => $summary,
                'next_article' => $nextArticle,
                'can_snooze' => $this->resolver->canSnoozeTraining($user),
                'snooze_hours' => LearnArkCurriculum::SNOOZE_HOURS,
            ], 423);
        }

        if ($nextArticle !== null) {
            $target = ArkademyUrls::isCutover()
                ? redirect()->away(ArkademyUrls::pageUrlOrHome(
                    $nextArticle['section']->key,
                    $nextArticle['slug'],
                ))
                : redirect()->route('operations.learn.show', [
                    'role' => $nextArticle['section']->key,
                    'article' => $nextArticle['slug'],
                ]);

            return $target->with('learn_gate', [
                'summary' => $summary,
                'next_title' => $nextArticle['title'],
            ]);
        }

        $fallback = ArkademyUrls::isCutover()
            ? redirect()->away(ArkademyUrls::homeUrl())
            : redirect()->route('operations.learn.index');

        return $fallback->with('learn_gate', ['summary' => $summary]);
    }
}
