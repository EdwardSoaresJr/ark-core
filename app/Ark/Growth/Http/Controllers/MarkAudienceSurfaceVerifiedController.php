<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Settings\AudienceSurfaceVerifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class MarkAudienceSurfaceVerifiedController
{
    public function __invoke(Request $request, string $surface): RedirectResponse
    {
        $allowed = array_keys(config('entity_health.audience_surfaces', []));

        abort_unless(in_array($surface, $allowed, true), 404);

        AudienceSurfaceVerifications::markVerified($surface);

        return redirect()
            ->route('growth.entity-health')
            ->with('status', 'Marked '.$surface.' as verified today.');
    }
}
