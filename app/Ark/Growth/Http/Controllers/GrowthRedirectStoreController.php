<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Models\GrowthRedirect;
use App\Ark\Growth\Redirects\RedirectResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class GrowthRedirectStoreController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_path' => ['required', 'string', 'max:255'],
            'to_path' => ['nullable', 'string', 'max:255'],
            'status_code' => ['required', 'integer', 'in:301,302,410'],
            'is_wildcard' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $redirect = new GrowthRedirect([
            ...$validated,
            'is_wildcard' => (bool) ($validated['is_wildcard'] ?? false),
            'is_active' => true,
        ]);

        if (! $redirect->isGone() && blank($redirect->to_path)) {
            return back()->withErrors(['to_path' => 'Destination path is required unless status is 410.']);
        }

        $resolver = app(RedirectResolver::class);
        if (! $redirect->isGone() && $resolver->wouldLoop($redirect, (string) $redirect->to_path)) {
            return back()->withErrors(['to_path' => 'This redirect would create a loop.']);
        }

        $redirect->from_path = '/'.trim($redirect->from_path, '/');
        if ($redirect->from_path === '//') {
            $redirect->from_path = '/';
        }

        $redirect->save();
        RedirectResolver::flushCache();

        return redirect()->route('growth.redirects.index')->with('status', 'Redirect saved.');
    }
}
