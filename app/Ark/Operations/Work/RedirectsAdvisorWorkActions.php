<?php

namespace App\Ark\Operations\Work;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RedirectsAdvisorWorkActions
{
    protected function redirectAfterWorkAction(Request $request, string $status): RedirectResponse
    {
        $redirect = $request->input('redirect');

        if (is_string($redirect) && $redirect !== '' && str_starts_with($redirect, url('/'))) {
            return redirect()->to($redirect)->with('status', $status);
        }

        $referer = $request->headers->get('referer');

        if (is_string($referer) && $referer !== '' && str_starts_with($referer, url('/'))) {
            return redirect()->to($referer)->with('status', $status);
        }

        return redirect()
            ->route('operations.index')
            ->with('status', $status);
    }
}
