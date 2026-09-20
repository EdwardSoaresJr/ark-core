<?php

namespace App\Ark\Operations\Learn;

use Illuminate\Http\Request;
use Illuminate\View\View;

class LearnArkPrintController
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $visibleSections = LearnArkCatalog::visibleSectionsFor($user);
        $articlesByRole = LearnArkCatalog::articlesByRoleFor($user);

        $picks = $this->normalizePicks($request->input('pick', []));

        if ($picks === []) {
            return view('operations.learn.print-select', [
                'visibleSections' => $visibleSections,
                'articlesByRole' => $articlesByRole,
            ]);
        }

        $articles = LearnArkCatalog::resolvePrintPicks($user, $picks);

        abort_if($articles->isEmpty(), 404);

        return view('operations.learn.print', [
            'articles' => $articles,
        ]);
    }

    /**
     * @return list<string>
     */
    private function normalizePicks(mixed $picks): array
    {
        if (! is_array($picks)) {
            $picks = filled($picks) ? [(string) $picks] : [];
        }

        return collect($picks)
            ->filter(fn (mixed $pick): bool => is_string($pick) && $pick !== '')
            ->unique()
            ->values()
            ->all();
    }
}
