<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\CancelScheduledOutboundMessagesAction;
use App\Ark\Operations\Communications\ScheduledOutboundMessage;
use App\Ark\Operations\Communications\ScheduledOutboundMessageStatus;
use App\Ark\Operations\Communications\ScheduledOutboundMessageType;
use App\Ark\Operations\Communications\ScheduledOutboundSmsProjection;
use App\Ark\Operations\Customers\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CancelScheduledOutboundSmsController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        CancelScheduledOutboundMessagesAction $cancel,
        ScheduledOutboundSmsProjection $scheduleProjection,
    ): JsonResponse {
        $pending = ScheduledOutboundMessage::query()
            ->where('customer_id', $customer->id)
            ->where('type', ScheduledOutboundMessageType::SmsReply)
            ->where('status', ScheduledOutboundMessageStatus::Scheduled)
            ->orderByDesc('id')
            ->first();

        if ($pending === null) {
            return response()->json([
                'cancelled' => false,
                'sms_schedule' => $scheduleProjection->forCustomer($customer->id),
                'message' => 'No scheduled reply to cancel.',
            ]);
        }

        $cancel->cancel($pending, $request->user());

        return response()->json([
            'cancelled' => true,
            'sms_schedule' => $scheduleProjection->forCustomer($customer->id),
            'message' => 'Scheduled reply cancelled.',
        ]);
    }
}
