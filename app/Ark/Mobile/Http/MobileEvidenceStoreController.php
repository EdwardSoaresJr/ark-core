<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Operations\Evidence\AttachEvidenceAction;
use App\Ark\Operations\Evidence\EvidenceAttachable;
use App\Ark\Operations\Evidence\EvidenceProjection;
use App\Ark\Operations\Evidence\EvidenceSource;
use App\Ark\Operations\Evidence\EvidenceStore;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class MobileEvidenceStoreController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        AttachEvidenceAction $attach,
        EvidenceAttachable $attachables,
        EvidenceProjection $projection,
    ): JsonResponse {
        abort_unless($request->user()?->can(ArkCapability::RepairOrdersManage->value), 403);
        $repairOrder->ensureOpenForEditing();

        $data = $request->validate([
            'file' => EvidenceStore::uploadRules(required: true),
            'attachable_kind' => ['required', Rule::in([
                EvidenceAttachable::KIND_CONCERN,
                EvidenceAttachable::KIND_REPAIR_ORDER,
            ])],
            'attachable_id' => ['required', 'integer'],
            'caption' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', Rule::enum(EvidenceSource::class)],
            'as_primary' => ['nullable', 'boolean'],
        ]);

        $kind = (string) $data['attachable_kind'];
        $attachableId = (int) $data['attachable_id'];
        if ($kind === EvidenceAttachable::KIND_REPAIR_ORDER) {
            $attachableId = (int) $repairOrder->id;
        }

        $attachable = $attachables->resolve($repairOrder, $kind, $attachableId);
        $source = isset($data['source'])
            ? EvidenceSource::from($data['source'])
            : EvidenceSource::Camera;

        $evidence = $attach->handle(
            $repairOrder,
            $attachable,
            $data['file'],
            $request->user(),
            $source,
            $data['caption'] ?? null,
            (bool) ($data['as_primary'] ?? false),
        );

        $row = $projection->forRepairOrder($repairOrder)->firstWhere('id', $evidence->id);

        return response()->json([
            'evidence' => $row,
        ], 201);
    }
}
