<?php

namespace App\Ark\Mobile\Http;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every mobile response about one repair order carries its current estimate version, including
 * mutations whose JSON body does not, so the app can send that version back as
 * opened_estimate_version on its next change and receive a 409 instead of overwriting newer work.
 */
final class ReportRepairOrderEstimateVersion
{
    public const HEADER = 'X-Ark-Estimate-Version';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $repairOrder = $request->route('repairOrder');

        if ($repairOrder instanceof RepairOrder && $repairOrder->exists) {
            $version = RepairOrder::query()->whereKey($repairOrder->getKey())->value('estimate_version');

            if ($version !== null) {
                $response->headers->set(self::HEADER, (string) (int) $version);
            }
        }

        return $response;
    }
}
