<?php

namespace App\Ark\Operations\Evidence;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Slice 1 morph map. Resolve attachable and enforce RO boundary.
 */
final class EvidenceAttachable
{
    public const KIND_CONCERN = 'concern';

    public const KIND_REPAIR_ORDER = 'repair_order';

    public static function morphClass(string $kind): string
    {
        return match ($kind) {
            self::KIND_CONCERN => RepairOrderConcern::class,
            self::KIND_REPAIR_ORDER => RepairOrder::class,
            default => throw new InvalidArgumentException('Unsupported evidence attachable.'),
        };
    }

    public static function kindFor(Model $attachable): string
    {
        return match ($attachable::class) {
            RepairOrderConcern::class => self::KIND_CONCERN,
            RepairOrder::class => self::KIND_REPAIR_ORDER,
            default => throw new InvalidArgumentException('Unsupported evidence attachable.'),
        };
    }

    public function resolve(RepairOrder $repairOrder, string $kind, int $attachableId): Model
    {
        $attachable = match ($kind) {
            self::KIND_CONCERN => RepairOrderConcern::query()->findOrFail($attachableId),
            self::KIND_REPAIR_ORDER => RepairOrder::query()->findOrFail($attachableId),
            default => throw new InvalidArgumentException('Unsupported evidence attachable.'),
        };

        $this->assertSameRepairOrder($repairOrder, $attachable);

        return $attachable;
    }

    public function repairOrderIdFor(Model $attachable): int
    {
        if ($attachable instanceof RepairOrder) {
            return (int) $attachable->id;
        }

        if ($attachable instanceof RepairOrderConcern) {
            return (int) $attachable->repair_order_id;
        }

        throw new InvalidArgumentException('Unsupported evidence attachable.');
    }

    public function assertSameRepairOrder(RepairOrder $repairOrder, Model $attachable): void
    {
        if ($this->repairOrderIdFor($attachable) !== (int) $repairOrder->id) {
            throw new InvalidArgumentException('Evidence and attachable must belong to the same repair order.');
        }
    }
}
