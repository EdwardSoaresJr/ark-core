<?php

namespace App\Ark\Platform\Parts;

use App\Ark\Operations\Parts\PartsTechCatalogLauncher;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;

final class PartsTechPlatformRequest
{
    /**
     * @param  array{sync_only?: bool, force_cart_switch?: bool, concern_id?: int|null, stamp_only?: bool}  $options
     * @return array<string, mixed>
     */
    public static function payload(RepairOrder $repairOrder, PartsTechCatalogLauncher $launcher, ?User $user, array $options = []): array
    {
        $repairOrder->loadMissing(['vehicle', 'customer']);

        $ymm = $launcher->ymmForRepairOrder($repairOrder) ?? [];
        $payload = [
            'cart_reference' => $launcher->partsTechCartReference($repairOrder),
            'shop_number' => $launcher->repairOrderNumber($repairOrder),
            'po_number' => $launcher->poNumber($repairOrder),
            'vin' => $launcher->vinForRepairOrder($repairOrder),
            'year' => $ymm['year'] ?? null,
            'make' => $ymm['make'] ?? null,
            'model' => $ymm['model'] ?? null,
            'trim' => $ymm['trim'] ?? null,
            'engine' => $ymm['engine'] ?? null,
            'customer_email' => filled($repairOrder->customer?->email)
                ? trim((string) $repairOrder->customer->email)
                : null,
            'concern_id' => $options['concern_id'] ?? null,
            'sync_only' => (bool) ($options['sync_only'] ?? false),
            'force_cart_switch' => (bool) ($options['force_cart_switch'] ?? false),
            'stamp_only' => (bool) ($options['stamp_only'] ?? false),
        ];

        if ($user !== null) {
            $payload['actor'] = [
                'core_user_id' => $user->id,
                'display_name' => $user->name,
            ];
        }

        if ($user !== null && $user->usesPersonalPartsTechLogin()) {
            $payload['partstech_seat'] = [
                'username' => trim((string) $user->partstech_username),
                'password' => (string) $user->partstech_password,
            ];
            $payload['login_username'] = $payload['partstech_seat']['username'];
            $payload['login_password'] = $payload['partstech_seat']['password'];
        }

        return $payload;
    }
}
