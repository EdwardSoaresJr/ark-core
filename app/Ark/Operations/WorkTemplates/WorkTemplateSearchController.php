<?php

namespace App\Ark\Operations\WorkTemplates;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Active Saved Work search for the Workspace Modal picker.
 */
final class WorkTemplateSearchController
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        $templates = WorkTemplate::query()
            ->active()
            ->with('lines')
            ->search($term)
            ->orderBy('position')
            ->orderBy('title')
            ->limit(25)
            ->get()
            ->map(fn (WorkTemplate $template): array => $template->previewPayload())
            ->values()
            ->all();

        return response()->json(['templates' => $templates]);
    }
}
