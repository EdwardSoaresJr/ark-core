<?php

namespace App\Ark\Operations\Workspace;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceTabActivityController
{
    public function __invoke(Request $request, WorkspaceTabActivityResolver $resolver): JsonResponse
    {
        if (! config('ark_workspace_tabs.enabled', true)) {
            return response()->json(['patches' => []]);
        }

        $data = $request->validate([
            'activeKey' => ['nullable', 'string', 'max:120'],
            'tabs' => ['required', 'array', 'max:12'],
            'tabs.*.key' => ['required', 'string', 'max:120'],
            'tabs.*.seen' => ['nullable', 'array'],
            'tabs.*.seen.estimateVersion' => ['nullable', 'integer', 'min:0'],
            'tabs.*.seen.movementAt' => ['nullable', 'string', 'max:64'],
            'tabs.*.seen.operationalState' => ['nullable', 'string', 'max:32'],
        ]);

        return response()->json([
            'patches' => $resolver->resolveMany(
                (string) ($data['activeKey'] ?? ''),
                $data['tabs'],
            ),
        ]);
    }
}
