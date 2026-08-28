<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Append-only durable customer doorway. Never mutates public_code on an existing row.
 */
final class CreateOrReuseRepairOrderPortalAccessAction
{
    /** Unambiguous alphabet (no 0/O/1/I/L). */
    private const CODE_ALPHABET = '23456789abcdefghjkmnpqrstuvwxyz';

    public function execute(RepairOrder $repairOrder, ?User $actor = null): RepairOrderPortalAccess
    {
        $existing = RepairOrderPortalAccess::query()
            ->where('repair_order_id', $repairOrder->id)
            ->active()
            ->orderBy('id')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($repairOrder, $actor): RepairOrderPortalAccess {
            $locked = RepairOrderPortalAccess::query()
                ->where('repair_order_id', $repairOrder->id)
                ->active()
                ->lockForUpdate()
                ->orderBy('id')
                ->first();

            if ($locked !== null) {
                return $locked;
            }

            $plainToken = Str::random(64);

            return RepairOrderPortalAccess::query()->create([
                'repair_order_id' => $repairOrder->id,
                'public_code' => $this->uniquePublicCode(),
                'token_hash' => RepairOrderPortalAccess::hashPlainToken($plainToken),
                'created_by_user_id' => $actor?->id,
            ]);
        });
    }

    /**
     * Compromise path: revoke active row, mint a new append-only row (new code).
     */
    public function revokeAndReplace(RepairOrder $repairOrder, ?User $actor = null): RepairOrderPortalAccess
    {
        return DB::transaction(function () use ($repairOrder, $actor): RepairOrderPortalAccess {
            RepairOrderPortalAccess::query()
                ->where('repair_order_id', $repairOrder->id)
                ->active()
                ->lockForUpdate()
                ->update(['revoked_at' => now()]);

            $plainToken = Str::random(64);

            return RepairOrderPortalAccess::query()->create([
                'repair_order_id' => $repairOrder->id,
                'public_code' => $this->uniquePublicCode(),
                'token_hash' => RepairOrderPortalAccess::hashPlainToken($plainToken),
                'created_by_user_id' => $actor?->id,
            ]);
        });
    }

    private function uniquePublicCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (RepairOrderPortalAccess::query()->where('public_code', $code)->exists());

        return $code;
    }
}
