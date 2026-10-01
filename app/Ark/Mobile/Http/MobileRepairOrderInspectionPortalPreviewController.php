<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Operations\Inspections\InspectionFindingCardProjection;
use App\Ark\Operations\Portal\CreateOrReuseInspectionAccessTokenAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MobileRepairOrderInspectionPortalPreviewController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        CreateOrReuseInspectionAccessTokenAction $tokens,
    ): JsonResponse {
        abort_unless($request->user()?->can(ArkCapability::RepairOrdersManage->value), 403);
        abort_unless(
            InspectionFindingCardProjection::recordedCountForRepairOrder($repairOrder) > 0,
            422,
            'Record at least one inspection finding before previewing.',
        );

        $accessToken = $tokens->execute($repairOrder, $request->user(), forStaffPreview: true);

        return response()->json([
            'url' => route('portal.inspections.show', ['token' => $accessToken->plainToken]),
        ]);
    }
}
