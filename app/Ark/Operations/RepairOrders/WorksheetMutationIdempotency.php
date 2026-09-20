<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Short-window dedupe for Builder double-submits.
 * Client sends worksheet_idempotency_key once per intentional action.
 */
final class WorksheetMutationIdempotency
{
    public const FIELD = 'worksheet_idempotency_key';

    private const TTL_SECONDS = 60;

    public static function keyFrom(Request $request): ?string
    {
        $key = trim((string) $request->input(self::FIELD, ''));

        if ($key === '' || strlen($key) > 80) {
            return null;
        }

        if (! preg_match('/^[A-Za-z0-9._:-]+$/', $key)) {
            return null;
        }

        return $key;
    }

    public static function cacheKey(RepairOrder $repairOrder, string $action, string $idempotencyKey): string
    {
        return 'ws.idem.v1.'.$repairOrder->id.'.'.$action.'.'.$idempotencyKey;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function recall(RepairOrder $repairOrder, string $action, ?string $idempotencyKey): ?array
    {
        if ($idempotencyKey === null) {
            return null;
        }

        $cached = Cache::get(self::cacheKey($repairOrder, $action, $idempotencyKey));

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function remember(RepairOrder $repairOrder, string $action, ?string $idempotencyKey, array $payload): void
    {
        if ($idempotencyKey === null) {
            return;
        }

        Cache::put(
            self::cacheKey($repairOrder, $action, $idempotencyKey),
            $payload,
            now()->addSeconds(self::TTL_SECONDS),
        );
    }
}
