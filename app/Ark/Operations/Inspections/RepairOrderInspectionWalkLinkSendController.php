<?php

namespace App\Ark\Operations\Inspections;

use App\Ark\Operations\Messaging\OutboundDeliveryMode;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class RepairOrderInspectionWalkLinkSendController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        SendInspectionWalkLinkAction $send,
    ): RedirectResponse|JsonResponse {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'delivery' => ['required', Rule::enum(OutboundDeliveryMode::class)],
        ]);

        $recipient = User::query()->findOrFail((int) $data['user_id']);
        $mode = OutboundDeliveryMode::from($data['delivery']);

        try {
            $result = $send->execute(
                $repairOrder,
                $request->user(),
                $recipient,
                $mode,
            );
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['walk_link' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $result['status_label'],
                'sms_sent' => $result['sms_sent'],
                'email_sent' => $result['email_sent'],
            ]);
        }

        return redirect()
            ->back()
            ->with('status', $result['status_label']);
    }
}
