<?php

namespace App\Ark\Platform;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Hidden master-admin read-only cluster list.
 * Not linked in navigation. No edit/actions.
 */
final class ClusterIndexController
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->isMasterAdmin(), 403);

        $clusters = Cluster::query()
            ->withCount('deployments')
            ->orderBy('name')
            ->get();

        return view('platform.clusters.index', [
            'clusters' => $clusters,
        ]);
    }
}
