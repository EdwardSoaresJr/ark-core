<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\Request;

/**
 * Disposable projection: which capture surface should open for this request.
 * Surfaces are projections of one inspection authority — not product concepts.
 *
 * Today: desktop walk · tablet bay shell.
 * Later: Companion · rugged kiosk · native mobile without renaming callers.
 */
final class InspectionCaptureSurfaceResolver
{
    public const DESKTOP_WALK = 'desktop_walk';

    public const TABLET = 'tablet';

    /**
     * @return list<string>
     */
    public static function surfaces(): array
    {
        return [
            self::DESKTOP_WALK,
            self::TABLET,
        ];
    }

    public static function resolve(?Request $request = null): string
    {
        $request ??= request();

        $forced = strtolower(trim((string) $request->query('capture_surface', '')));

        if (in_array($forced, self::surfaces(), true)) {
            return $forced;
        }

        if (self::prefersBayCaptureShell($request)) {
            return self::TABLET;
        }

        return self::DESKTOP_WALK;
    }

    /**
     * @return array{
     *     surface: string,
     *     url: string,
     *     desktop_walk_url: string,
     *     tablet_url: string,
     * }
     */
    public static function forRepairOrder(RepairOrder $repairOrder, ?Request $request = null, ?int $pointId = null): array
    {
        $surface = self::resolve($request);
        $desktopWalkUrl = InspectionCaptureLinks::walkUrl($repairOrder, $pointId);
        $tabletUrl = InspectionCaptureLinks::tabletUrl($repairOrder, $pointId);

        return [
            'surface' => $surface,
            'url' => $surface === self::TABLET ? $tabletUrl : $desktopWalkUrl,
            'desktop_walk_url' => $desktopWalkUrl,
            'tablet_url' => $tabletUrl,
        ];
    }

    public static function url(RepairOrder $repairOrder, ?Request $request = null, ?int $pointId = null): string
    {
        return self::forRepairOrder($repairOrder, $request, $pointId)['url'];
    }

    /**
     * Bay / handheld capture prefers the chromeless shell.
     * Desk browsers prefer the ops-chrome section walk.
     */
    private static function prefersBayCaptureShell(Request $request): bool
    {
        $ua = strtolower((string) $request->userAgent());

        if ($ua === '') {
            return false;
        }

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return true;
        }

        // Phones at the vehicle are capture devices — not desk review.
        if (str_contains($ua, 'iphone') || str_contains($ua, 'ipod')) {
            return true;
        }

        if (str_contains($ua, 'android') && str_contains($ua, 'mobile')) {
            return true;
        }

        // Android tablets often omit "mobile".
        if (str_contains($ua, 'android') && ! str_contains($ua, 'mobile')) {
            return true;
        }

        return false;
    }
}
