<?php

namespace App\Ark\Dragon\Bridge;

use App\Ark\Dragon\Assist\CompleteHostedDragonAssistAction;
use App\Ark\Dragon\Assist\DragonAssistRequest;

/**
 * Assist dispatch is ARK-hosted Dragon only. Remote nodes are not used.
 */
final class DragonBridgeDispatcher
{
    public function dispatchEligible(DragonAssistRequest $request): void
    {
        if ($request->status->isTerminal() || $request->isExpired()) {
            return;
        }

        app(CompleteHostedDragonAssistAction::class)->execute($request);
    }
}
