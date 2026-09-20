<?php

namespace App\Ark\Operations\Workspace;

use Illuminate\Http\Request;

final class WorkspaceTabBootEnricher
{
    public function forRequest(Request $request): ?array
    {
        $boot = WorkspaceTabSupport::detectFromRequest($request);

        return WorkspaceTabSupport::enrichBoot($boot);
    }
}
