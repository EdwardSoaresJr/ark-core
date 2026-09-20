<?php

namespace App\Ark\Operations\Communications;

/**
 * Communications daytime home — Needs attention filter on the unified inbox.
 *
 * One instinctive destination when Sarah texts: Communications → Needs attention.
 */
final class CommunicationsNeedsYou
{
    public static function routeName(): string
    {
        return 'operations.communications.inbox';
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function params(array $params = []): array
    {
        unset($params['filter'], $params['turn']);

        return ['filter' => 'needs'] + $params;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function url(array $params = []): string
    {
        return route(self::routeName(), self::params($params));
    }
}
