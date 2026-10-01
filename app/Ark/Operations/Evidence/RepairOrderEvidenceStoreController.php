<?php

namespace App\Ark\Operations\Evidence;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RepairOrderEvidenceStoreController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        AttachEvidenceAction $attach,
        EvidenceAttachable $attachables,
    ): RedirectResponse {
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
            : EvidenceSource::Upload;

        $attach->handle(
            $repairOrder,
            $attachable,
            $data['file'],
            $request->user(),
            $source,
            $data['caption'] ?? null,
            (bool) ($data['as_primary'] ?? false),
        );

        return redirect()
            ->route('operations.repair-orders.show', $repairOrder)
            ->withFragment('evidence-gallery')
            ->with('status', 'Evidence attached.');
    }
}
