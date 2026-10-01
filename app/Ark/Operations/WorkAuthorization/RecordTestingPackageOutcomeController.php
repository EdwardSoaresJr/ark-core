<?php

namespace App\Ark\Operations\WorkAuthorization;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class RecordTestingPackageOutcomeController
{
    public function create(RepairOrder $repairOrder, WorkAuthorization $workAuthorization): View
    {
        $this->assertBelongs($repairOrder, $workAuthorization);

        return view('operations.work-authorization.record-testing-outcome', [
            'repairOrder' => $repairOrder,
            'authorization' => $workAuthorization->loadMissing(['concern', 'workGroup']),
            'outcomes' => TestingPackageOutcome::cases(),
        ]);
    }

    public function store(
        Request $request,
        RepairOrder $repairOrder,
        WorkAuthorization $workAuthorization,
        RecordTestingPackageOutcomeAction $action,
    ): RedirectResponse {
        $this->assertBelongs($repairOrder, $workAuthorization);

        $validated = $request->validate([
            'outcome' => ['required', 'string', Rule::enum(TestingPackageOutcome::class)],
            'recommendation' => ['nullable', 'string', 'max:2000'],
        ]);

        $action->handle(
            $workAuthorization,
            $request->user(),
            TestingPackageOutcome::from($validated['outcome']),
            $validated['recommendation'] ?? null,
        );

        return redirect()
            ->to(route('operations.repair-orders.show', $repairOrder).'#work-authorization')
            ->with('status', 'Testing Package outcome recorded.');
    }

    private function assertBelongs(RepairOrder $repairOrder, WorkAuthorization $workAuthorization): void
    {
        abort_unless(
            (int) $workAuthorization->repair_order_id === (int) $repairOrder->id,
            404,
        );
    }
}
