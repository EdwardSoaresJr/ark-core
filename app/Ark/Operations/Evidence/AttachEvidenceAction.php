<?php

namespace App\Ark\Operations\Evidence;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttachEvidenceAction
{
    public function __construct(
        private readonly EvidenceStore $store,
        private readonly EvidenceAttachable $attachables,
        private readonly ChangeEvidenceVisibilityAction $visibility,
    ) {}

    public function handle(
        RepairOrder $repairOrder,
        Model $attachable,
        UploadedFile $file,
        User $actor,
        EvidenceSource $source = EvidenceSource::Upload,
        ?string $caption = null,
        bool $asPrimary = false,
    ): Evidence {
        $this->attachables->assertSameRepairOrder($repairOrder, $attachable);
        $repairOrder->ensureOpenForEditing();

        $caption = $caption !== null ? trim($caption) : null;
        if ($caption === '') {
            $caption = null;
        }

        if ($caption !== null && mb_strlen($caption) > 500) {
            throw ValidationException::withMessages([
                'caption' => 'Caption may be at most 500 characters.',
            ]);
        }

        return DB::transaction(function () use ($repairOrder, $attachable, $file, $actor, $source, $caption, $asPrimary): Evidence {
            $stored = $this->store->storeFile($repairOrder, $file);

            $nextSort = (int) Evidence::query()
                ->where('repair_order_id', $repairOrder->id)
                ->withTrashed()
                ->max('sort_order') + 1;

            $evidence = Evidence::query()->create([
                'repair_order_id' => $repairOrder->id,
                'type' => $stored['type'],
                'source' => $source,
                'storage_path' => $stored['storage_path'],
                'content_type' => $stored['content_type'],
                'original_name' => $stored['original_name'],
                'byte_size' => $stored['byte_size'],
                'uploaded_by_user_id' => $actor->id,
                'taken_at' => now(),
                'caption' => $caption,
                'visibility' => EvidenceVisibility::Internal,
                'sort_order' => $nextSort,
            ]);

            $this->visibility->recordInitial($evidence, $actor);

            $attachment = EvidenceAttachment::query()->create([
                'evidence_id' => $evidence->id,
                'attachable_type' => $attachable::class,
                'attachable_id' => $attachable->getKey(),
                'is_primary' => false,
            ]);

            if ($asPrimary) {
                app(SetPrimaryEvidenceAction::class)->handle($repairOrder, $attachment, $actor);
            }

            return $evidence->fresh(['attachments']) ?? $evidence;
        });
    }
}
