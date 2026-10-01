<?php

namespace App\Ark\Operations\Contribution;

use App\Ark\Operations\Inspections\InspectionItemPhoto;
use App\Ark\Operations\Inspections\ValidatesInspectionScope;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ContributeInspectionPhotoController
{
    use ValidatesInspectionScope;

    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InspectionItemPhoto $photo,
        ContributeEvidence $contributeEvidence,
    ): RedirectResponse {
        abort_unless(
            $request->user()?->can(ArkCapability::RepairOrdersManage->value)
                || $request->user()?->can(ArkCapability::SettingsManage->value),
            403,
        );

        $photo = $this->photoForRepairOrder($repairOrder, $photo);

        $data = $request->validate([
            'common_problem_slug' => ['required', 'string', 'max:120'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $contributeEvidence->contributeInspectionPhoto(
            $repairOrder,
            $photo,
            (string) $data['common_problem_slug'],
            $request->user(),
            isset($data['caption']) ? (string) $data['caption'] : null,
        );

        return redirect()
            ->back()
            ->with('status', 'Evidence contributed to the website page.');
    }
}
