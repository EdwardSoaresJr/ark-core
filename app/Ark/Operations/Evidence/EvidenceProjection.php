<?php

namespace App\Ark\Operations\Evidence;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Disposable packaging of Evidence for staff / customer surfaces.
 * One renderer family — never per-domain photo renderers.
 */
final class EvidenceProjection
{
    public function __construct(
        private readonly EvidenceAttachable $attachables,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forRepairOrder(RepairOrder $repairOrder, bool $customerFacing = false): Collection
    {
        $query = Evidence::query()
            ->with(['attachments', 'uploadedBy'])
            ->where('repair_order_id', $repairOrder->id)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($customerFacing) {
            $query->where('visibility', EvidenceVisibility::Shared->value);
        }

        return $query->get()->map(fn (Evidence $evidence): array => $this->present($evidence, $repairOrder, $customerFacing));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forAttachable(RepairOrder $repairOrder, Model $attachable, bool $customerFacing = false): Collection
    {
        $this->attachables->assertSameRepairOrder($repairOrder, $attachable);

        $ids = EvidenceAttachment::query()
            ->where('attachable_type', $attachable::class)
            ->where('attachable_id', $attachable->getKey())
            ->pluck('evidence_id');

        $query = Evidence::query()
            ->with(['attachments', 'uploadedBy'])
            ->where('repair_order_id', $repairOrder->id)
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($customerFacing) {
            $query->where('visibility', EvidenceVisibility::Shared->value);
        }

        return $query->get()->map(fn (Evidence $evidence): array => $this->present($evidence, $repairOrder, $customerFacing));
    }

    /**
     * Primary for attachable, with deterministic fallback.
     *
     * @return array<string, mixed>|null
     */
    public function primaryForAttachable(RepairOrder $repairOrder, Model $attachable, bool $customerFacing = false): ?array
    {
        $items = $this->forAttachable($repairOrder, $attachable, $customerFacing);
        if ($items->isEmpty()) {
            return null;
        }

        $primaryId = EvidenceAttachment::query()
            ->where('attachable_type', $attachable::class)
            ->where('attachable_id', $attachable->getKey())
            ->where('is_primary', true)
            ->value('evidence_id');

        if ($primaryId !== null) {
            $primary = $items->firstWhere('id', (int) $primaryId);
            if ($primary !== null) {
                return $primary;
            }
        }

        return $items->first();
    }

    /**
     * Staff gallery rows with filter metadata.
     *
     * @return array{items: Collection<int, array<string, mixed>>, concerns: Collection<int, array{id: int, summary: string}>}
     */
    public function staffGallery(RepairOrder $repairOrder): array
    {
        $items = $this->forRepairOrder($repairOrder, customerFacing: false);
        $concerns = $repairOrder->relationLoaded('concerns')
            ? $repairOrder->concerns
            : $repairOrder->concerns()->orderBy('position')->get();

        return [
            'items' => $items,
            'concerns' => $concerns->map(fn (RepairOrderConcern $c): array => [
                'id' => $c->id,
                'summary' => $c->summary,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Evidence $evidence, RepairOrder $repairOrder, bool $customerFacing): array
    {
        $attachment = $evidence->attachments->first();
        $kind = null;
        $attachableId = null;
        $isPrimary = false;

        if ($attachment !== null) {
            $kind = match ($attachment->attachable_type) {
                RepairOrderConcern::class => EvidenceAttachable::KIND_CONCERN,
                RepairOrder::class => EvidenceAttachable::KIND_REPAIR_ORDER,
                default => null,
            };
            $attachableId = (int) $attachment->attachable_id;
            $isPrimary = (bool) $attachment->is_primary;
        }

        $staffUrl = route('operations.repair-orders.evidence.show', [$repairOrder, $evidence]);
        $portalUrl = $customerFacing
            ? null
            : null;

        return [
            'id' => $evidence->id,
            'type' => $evidence->type->value,
            'type_label' => $evidence->type->label(),
            'source' => $evidence->source->value,
            'caption' => $evidence->caption,
            'visibility' => $evidence->visibility->value,
            'visibility_label' => $evidence->visibility->label(),
            'shared_at' => $evidence->shared_at?->toIso8601String(),
            'first_customer_viewed_at' => $evidence->first_customer_viewed_at?->toIso8601String(),
            'sort_order' => $evidence->sort_order,
            'is_image' => $evidence->isImage(),
            'is_video' => $evidence->isVideo(),
            'is_pdf' => $evidence->isPdf(),
            'content_type' => $evidence->content_type,
            'original_name' => $evidence->original_name,
            'uploaded_by' => $evidence->uploadedBy?->name,
            'attachable_kind' => $kind,
            'attachable_id' => $attachableId,
            'is_primary' => $isPrimary,
            'filter' => $kind === EvidenceAttachable::KIND_CONCERN
                ? 'concern:'.$attachableId
                : 'general',
            'url' => $staffUrl,
            'attachment_id' => $attachment?->id,
        ];
    }
}
