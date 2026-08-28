<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Communications\CancelScheduledOutboundMessagesAction;
use App\Ark\Operations\Communications\ScheduleOutboundEstimateAction;
use App\Ark\Operations\Communications\ScheduledOutboundEstimateProjection;
use App\Ark\Operations\Communications\TomorrowMorningSchedule;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class SendEstimateLinkController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        SendEstimateDeliveryAction $send,
        ScheduleOutboundEstimateAction $schedule,
        CancelScheduledOutboundMessagesAction $cancelPending,
        ScheduledOutboundEstimateProjection $scheduleProjection,
        ConversationDeliveryJsonResponse $response,
    ): JsonResponse {
        $data = $request->validate([
            'delivery' => ['nullable', Rule::enum(OutboundDeliveryMode::class)],
            'timing' => ['nullable', 'string', Rule::in(['now', 'tomorrow_morning'])],
            'scheduled_for' => ['nullable', 'string', 'max:64'],
            'email' => [
                'exclude_unless:delivery,email,both',
                'nullable',
                'string',
                'lowercase',
                'email',
                'max:255',
            ],
            'message' => ['nullable', 'string', 'max:500'],
            'acknowledge_missing_vin' => ['nullable', 'boolean'],
            'acknowledge_timing_fluids' => ['nullable', 'boolean'],
        ]);

        $mode = OutboundDeliveryMode::tryFrom((string) ($data['delivery'] ?? OutboundDeliveryMode::Sms->value))
            ?? OutboundDeliveryMode::Sms;
        $timing = (string) ($data['timing'] ?? 'now');
        $wantsSchedule = $timing === 'tomorrow_morning' || filled($data['scheduled_for'] ?? null);

        if ($mode->includesEmail() && ! $request->user()?->can(ArkCapability::RepairOrdersManage->value)) {
            return response()->json(['message' => 'You do not have permission to email estimates.'], 403);
        }

        try {
            if ($wantsSchedule) {
                $scheduledFor = TomorrowMorningSchedule::resolveAllowedInstant(
                    $data['scheduled_for'] ?? null,
                );

                $row = $schedule->execute(
                    $repairOrder,
                    $request->user(),
                    $mode,
                    $data['email'] ?? null,
                    $data['message'] ?? null,
                    $request->boolean('acknowledge_missing_vin'),
                    scheduledFor: $scheduledFor,
                    acknowledgeTimingFluids: $request->boolean('acknowledge_timing_fluids'),
                );

                $schedulePayload = $scheduleProjection->forRepairOrder($repairOrder->id);
                $label = $schedulePayload['pending']['scheduled_for_label'] ?? 'next open morning';

                return response()->json([
                    'scheduled' => true,
                    'scheduled_outbound_message_id' => $row->id,
                    'scheduled_for' => $row->scheduled_for?->utc()->toIso8601String(),
                    'schedule' => $schedulePayload,
                    'message' => "Estimate scheduled for {$label}.",
                ]);
            }

            $cancelPending->forRepairOrderEstimate($repairOrder, $request->user());

            $result = $send->execute(
                $repairOrder,
                $request->user(),
                $mode,
                $data['email'] ?? null,
                $data['message'] ?? null,
                $request->boolean('acknowledge_missing_vin'),
                acknowledgeTimingFluids: $request->boolean('acknowledge_timing_fluids'),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return $response->make($result['messages'], array_filter([
            'estimate_url' => $result['estimate_url'],
            'token_reused' => $result['token_reused'],
            'scheduled' => false,
            'schedule' => $scheduleProjection->forRepairOrder($repairOrder->id),
            'awaiting_approval' => $result['awaiting_approval'],
        ], fn (mixed $value): bool => $value !== null));
    }
}
