<?php

namespace App\Http\Controllers;

use App\Ark\Runtime\Preferences\DisplayTheme;
use App\Ark\Runtime\Preferences\EcosystemDisplayTheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DisplayThemeController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'display_theme' => ['required', Rule::in(DisplayTheme::values())],
        ]);

        $theme = DisplayTheme::from($validated['display_theme']);

        $user = $request->user();
        $user->display_theme = $theme->value;
        $user->save();

        EcosystemDisplayTheme::queueForUser($theme);

        $systemPrefersDark = strtolower((string) $request->headers->get('Sec-CH-Prefers-Color-Scheme', '')) === 'dark';

        return response()->json([
            'display_theme' => $theme->value,
            'dark' => $user->prefersDarkDisplay($systemPrefersDark),
        ]);
    }
}
