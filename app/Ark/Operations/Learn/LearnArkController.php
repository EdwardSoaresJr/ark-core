<?php

namespace App\Ark\Operations\Learn;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LearnArkController
{
    public function __construct(
        private readonly LearnArkProgressResolver $progress,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (ArkademyUrls::isCutover()) {
            $next = $this->progress->nextRequiredArticle($user)
                ?? LearnArkCatalog::defaultArticleFor($user);

            if ($next !== null) {
                return redirect()->away(ArkademyUrls::pageUrlOrHome(
                    $next['section']->key,
                    $next['slug'],
                ));
            }

            return redirect()->away(ArkademyUrls::homeUrl());
        }

        $default = $this->progress->nextRequiredArticle($user)
            ?? LearnArkCatalog::defaultArticleFor($user);

        if ($default === null) {
            return view('operations.learn.index', $this->viewData($user, null));
        }

        return redirect()->route('operations.learn.show', [
            'role' => $default['section']->key,
            'article' => $default['slug'],
        ]);
    }

    public function show(Request $request, string $role, string $article): View|RedirectResponse
    {
        if (ArkademyUrls::isCutover()) {
            return redirect()->away(ArkademyUrls::pageUrlOrHome($role, $article));
        }

        $user = $request->user();
        $resolved = LearnArkCatalog::articleFor($user, $role, $article);

        if ($resolved === null) {
            abort(404);
        }

        return view('operations.learn.index', $this->viewData($user, $resolved));
    }

    /**
     * @param  array{section: LearnArkSection, slug: string, title: string, summary: string, view: string}|null  $article
     * @return array<string, mixed>
     */
    private function viewData(\App\Models\User $user, ?array $article): array
    {
        $visibleSections = LearnArkCatalog::visibleSectionsFor($user);
        $articlesByRole = LearnArkCatalog::articlesByRole();
        $completions = LearnCompletion::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('article_key');

        $completedArticleKeys = $completions
            ->filter(fn (LearnCompletion $completion, string $articleKey): bool => LearnArkCurriculum::completionIsCurrent($completion, $articleKey))
            ->keys()
            ->all();

        $staleArticleKeys = $completions
            ->filter(fn (LearnCompletion $completion, string $articleKey): bool => ! LearnArkCurriculum::completionIsCurrent($completion, $articleKey))
            ->keys()
            ->all();

        return [
            'visibleSections' => $visibleSections,
            'articlesByRole' => collect($articlesByRole)
                ->only(collect($visibleSections)->map->key->all())
                ->all(),
            'article' => $article,
            'articleMedia' => $article !== null
                ? LearnArticleMedia::query()
                    ->where('article_key', LearnArticleKey::make($article['section']->key, $article['slug']))
                    ->orderBy('slot')
                    ->get()
                : collect(),
            'canManageLearnMedia' => $user->can(\App\Ark\Runtime\Authorization\ArkCapability::SettingsManage->value),
            'trainingSummary' => $this->progress->summaryFor($user),
            'trainingProgress' => $this->progress->requiredProgressFor($user),
            'completedArticleKeys' => $completedArticleKeys,
            'staleArticleKeys' => $staleArticleKeys,
            'articleProgress' => $article !== null
                ? $this->progress->articleState($user, LearnArticleKey::make($article['section']->key, $article['slug']))
                : null,
            'trainingSnooze' => $this->progress->snoozeState($user),
            'canSnoozeTraining' => $this->progress->canSnoozeTraining($user),
            'snoozeHours' => LearnArkCurriculum::SNOOZE_HOURS,
            'nextWaveArticles' => LearnArkCurriculum::nextWaveArticlesFor($user),
            'trainingGateEnabled' => LearnArkTrainingGate::isShopEnabled(),
            'canManageTrainingGate' => $user->isMasterAdmin(),
            'ownerTrainingBypass' => LearnArkTrainingGate::ownerBypasses($user),
        ];
    }
}
