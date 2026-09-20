<?php

namespace App\Ark\Mobile;

/**
 * Companion v1 deep-link routes — push, continuity, and notification payloads.
 *
 * Flutter router owns navigation; these strings are stable contracts.
 */
final class MobileCompanionDeepLink
{
    public static function home(): string
    {
        return 'companion://home';
    }

    public static function conversation(int $conversationId): string
    {
        return 'companion://conversations/'.$conversationId;
    }

    public static function repairOrder(int $repairOrderId): string
    {
        return 'companion://repair-orders/'.$repairOrderId;
    }

    public static function repairOrderInspection(int $repairOrderId): string
    {
        return 'companion://repair-orders/'.$repairOrderId.'/inspection';
    }

    public static function customer(int $customerId): string
    {
        return 'companion://customers/'.$customerId;
    }

    public static function vehicle(int $vehicleId): string
    {
        return 'companion://vehicles/'.$vehicleId;
    }

    public static function inspectionItem(int $repairOrderId, int $itemId): string
    {
        return 'companion://repair-orders/'.$repairOrderId.'/inspection/items/'.$itemId;
    }

    public static function calls(?int $callSessionId = null): string
    {
        if ($callSessionId === null) {
            return 'companion://calls';
        }

        return 'companion://calls/'.$callSessionId;
    }

    public static function incomingCall(?int $callSessionId = null, ?string $phone = null): string
    {
        $query = [];

        if ($callSessionId !== null) {
            $query['call_session_id'] = $callSessionId;
        }

        if ($phone !== null && $phone !== '') {
            $query['phone'] = $phone;
        }

        if ($query === []) {
            return 'companion://incoming-call';
        }

        return 'companion://incoming-call?'.http_build_query($query);
    }

    public static function search(?string $query = null): string
    {
        if ($query === null || $query === '') {
            return 'companion://search';
        }

        return 'companion://search?'.http_build_query(['q' => $query]);
    }

    public static function schedule(?string $date = null): string
    {
        if ($date === null || $date === '') {
            return 'companion://schedule';
        }

        return 'companion://schedule?'.http_build_query(['date' => $date]);
    }
}
