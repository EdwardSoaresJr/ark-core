<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobBoardLaneSettingsController
{
    public function __invoke(Request $request, JobBoardLaneCatalogUpdater $updater): RedirectResponse
    {
        $data = $request->validate([
            'create' => ['nullable', 'array'],
            'create.name' => ['nullable', 'string', 'max:64'],
            'create.color' => ['nullable', 'string', Rule::in(RepairOrderStatusColor::keys())],
            'lanes' => ['nullable', 'array'],
            'lanes.*.name' => ['nullable', 'string', 'max:64'],
            'lanes.*.color' => ['nullable', 'string', Rule::in(RepairOrderStatusColor::keys())],
            'lanes.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'lanes.*.active' => ['nullable'],
        ]);

        $updater->apply($data);

        $message = filled(trim((string) ($data['create']['name'] ?? '')))
            ? 'Job Board lane added.'
            : 'Job Board lanes updated.';

        return redirect()
            ->route('operations.settings.shop.edit', [
                'section' => 'workflow',
                'workflow-tab' => 'statuses',
            ])
            ->with('status', $message);
    }
}
