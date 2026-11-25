<?php

namespace App\Http\Controllers;

use App\Ark\Operations\Workboard\JobBoardLayout;
use App\Ark\Runtime\Preferences\JobBoardLayoutPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobBoardViewPreferenceController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_board_view' => ['required', Rule::in([
                JobBoardLayout::List->value,
                JobBoardLayout::Cards->value,
            ])],
        ]);

        $layout = JobBoardLayout::parse($validated['job_board_view']);
        $user = $request->user();
        JobBoardLayoutPreference::persist($user, $layout);

        return response()->json([
            'job_board_view' => $layout->value,
        ]);
    }
}
