<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class RepairOrderWorkspaceTabController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        string $tab,
        RepairOrderWorkspaceTabPresenter $presenter,
    ): View {
        $workspaceMode = $request->query('mode', 'review') === 'builder' ? 'builder' : 'review';

        $repairOrder = $presenter->loadRepairOrderForTab($repairOrder, $tab);

        return view('operations.repair-orders.workspace-tabs.panel', [
            'tab' => $tab,
            ...$presenter->for($request, $repairOrder, $tab, $workspaceMode),
        ]);
    }
}
