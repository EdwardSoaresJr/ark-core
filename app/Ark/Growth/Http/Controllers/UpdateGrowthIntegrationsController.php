<?php

namespace App\Ark\Growth\Http\Controllers;

use App\Ark\Growth\Actions\UpdateGrowthIntegrationsAction;
use App\Http\Requests\Growth\UpdateGrowthIntegrationsRequest;
use Illuminate\Http\RedirectResponse;

final class UpdateGrowthIntegrationsController
{
    public function __invoke(
        UpdateGrowthIntegrationsRequest $request,
        UpdateGrowthIntegrationsAction $action,
    ): RedirectResponse {
        $action->execute(
            $request->validated(),
            $request->shouldBackfillAfterSave(),
        );

        $message = $request->shouldBackfillAfterSave()
            ? 'Growth integrations saved. Business Profile backfill queued.'
            : 'Growth integrations saved.';

        return redirect()
            ->route('growth.integrations.index')
            ->with('status', $message);
    }
}
