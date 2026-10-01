<?php

namespace App\Ark\Runtime\Ecosystem;

use App\Ark\Operations\Learn\ArkademyUrls;

/**
 * Deterministic ARK V2 → ARKademy links (no AI matching, no progress sync).
 */
final class EcosystemArkademyBridge
{
    public static function advisorGettingStartedUrl(): string
    {
        return ArkademyUrls::pageUrlOrHome('advisor', 'getting-started');
    }

    public static function advisorIncomingCallsUrl(): string
    {
        return ArkademyUrls::pageUrlOrHome('advisor', 'incoming-calls-floor');
    }

    public static function advisorCommsQueueUrl(): string
    {
        return ArkademyUrls::pageUrlOrHome('advisor', 'comms-queue');
    }
}
