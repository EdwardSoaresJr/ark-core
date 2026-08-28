<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepairOrderPartsTechCatalogController
{
    public function prepare(
        Request $request,
        RepairOrder $repairOrder,
        PartsTechCatalogLauncher $launcher,
        PartsTechCartPreparer $preparer,
    ): JsonResponse {
        $repairOrder->ensureOpenForEditing();
        $repairOrder->refresh();
        $repairOrder->load('vehicle');

        $catalogUrl = $launcher->launchUrl(
            $repairOrder,
            $request->integer('concern_id') ?: null,
        );

        if ($catalogUrl === null) {
            return response()->json([
                'prepared' => false,
                'catalog_url' => null,
                'message' => $launcher->blockedReason($repairOrder),
            ], 422);
        }

        try {
            $prepared = ! $preparer->canPrepare() || $preparer->prepareOrReport(
                $repairOrder,
                $request->boolean('sync_only'),
                $request->boolean('force_cart_switch'),
            );
        } catch (PartsTechShopSessionLockedException $exception) {
            return response()->json([
                'prepared' => false,
                'catalog_url' => $catalogUrl,
                'repair_order_number' => $launcher->repairOrderNumber($repairOrder),
                'cart_reference' => $launcher->partsTechCartReference($repairOrder),
                'message' => $exception->getMessage(),
                'cart_locked' => true,
                'blocking_cart_reference' => $exception->blockingCartReference,
            ], 423);
        }

        $message = null;

        if ($preparer->canPrepare() && ! $prepared) {
            $message = $preparer->lastFailureMessage()
                ?? 'PartsTech cart could not be prepared. Sign into PartsTech in your browser as '.$preparer->actingLoginUsername().', then retry.';
        }

        return response()->json([
            'prepared' => $prepared,
            'catalog_url' => $catalogUrl,
            'repair_order_number' => $launcher->repairOrderNumber($repairOrder),
            'cart_reference' => $launcher->partsTechCartReference($repairOrder),
            'partstech_login' => $preparer->canPrepare() ? $preparer->actingLoginUsername() : null,
            'partstech_login_source' => $preparer->canPrepare() && $preparer->usesPersonalLogin() ? 'user' : 'shop',
            'warnings' => $preparer->canPrepare() ? $preparer->lastWarnings() : [],
            'message' => $message,
        ]);
    }

    public function redirect(
        Request $request,
        RepairOrder $repairOrder,
        PartsTechCatalogLauncher $launcher,
        PartsTechCartPreparer $preparer,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $repairOrder->refresh();
        $repairOrder->load('vehicle');

        $url = $launcher->launchUrl(
            $repairOrder,
            $request->integer('concern_id') ?: null,
        );

        abort_if($url === null, 422, $launcher->blockedReason($repairOrder));

        if ($preparer->canPrepare()) {
            $preparer->prepareOrReport($repairOrder);
        }

        return redirect()->away($url);
    }
}
