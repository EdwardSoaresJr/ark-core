<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Attention\AdvisorNudgeResponseKind;
use App\Ark\Operations\Attention\RecordAdvisorNudgeResponseAction;
use App\Ark\Operations\Communications\CancelScheduledOutboundMessagesAction;
use App\Ark\Operations\Communications\ScheduleOutboundSmsReplyAction;
use App\Ark\Operations\Communications\ScheduledOutboundSmsProjection;
use App\Ark\Operations\Communications\TomorrowMorningSchedule;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Customers\CustomerHubCommsTimeline;
use App\Ark\Operations\Messaging\Messenger\MetaMessengerMessageTag;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use RuntimeException;

class SendConversationMessageController
{
    public function __invoke(
        Request $request,
        Customer $customer,
        SendOutboundMessageAction $sendSms,
        SendOutboundMessengerAction $sendMessenger,
        ScheduleOutboundSmsReplyAction $scheduleSms,
        CancelScheduledOutboundMessagesAction $cancelPendingSms,
        ScheduledOutboundSmsProjection $smsScheduleProjection,
        ConversationMessagePresenter $presenter,
        ConversationMessageRenderer $renderer,
        RecordAdvisorNudgeResponseAction $recordNudge,
        CustomerHubCommsTimeline $hubCommsTimeline,
    ): JsonResponse {
        $validated = $request->validate([
            'channel' => ['nullable', 'string', Rule::in(['sms', 'messenger'])],
            'timing' => ['nullable', 'string', Rule::in(['now', 'tomorrow_morning'])],
            'scheduled_for' => ['nullable', 'string', 'max:64'],
            'body' => ['nullable', 'string', 'max:1600'],
            'repair_order_id' => ['nullable', 'integer'],
            'messenger_message_tag' => ['nullable', 'string', Rule::enum(MetaMessengerMessageTag::class)],
            'nudge_key' => ['nullable', 'string', 'max:64'],
            'entity_key' => ['nullable', 'string', 'max:64'],
            'attachment' => [
                'nullable',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'pdf'])
                    ->max(OutboundAttachmentStore::MAX_BYTES / 1024),
            ],
        ]);

        $repairOrder = null;

        if (filled($validated['repair_order_id'] ?? null)) {
            $repairOrder = RepairOrder::query()
                ->where('repair_order_id', $validated['repair_order_id'])
                ->where('customer_id', $customer->id)
                ->first();

            abort_if($repairOrder === null, 422, 'Repair order does not belong to this customer.');
        }

        $channel = (string) ($validated['channel'] ?? 'sms');
        $timing = (string) ($validated['timing'] ?? 'now');
        $wantsSchedule = $timing === 'tomorrow_morning' || filled($validated['scheduled_for'] ?? null);

        if ($wantsSchedule) {
            if ($channel !== 'sms') {
                return response()->json(['message' => 'Only SMS replies can be scheduled.'], 422);
            }

            if ($request->file('attachment') !== null) {
                return response()->json(['message' => 'Remove the attachment to schedule this reply.'], 422);
            }

            try {
                $scheduledFor = TomorrowMorningSchedule::resolveAllowedInstant(
                    $validated['scheduled_for'] ?? null,
                );

                $row = $scheduleSms->execute(
                    customer: $customer,
                    actor: $request->user(),
                    body: (string) ($validated['body'] ?? ''),
                    repairOrder: $repairOrder,
                    scheduledFor: $scheduledFor,
                );
            } catch (RuntimeException $exception) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            $smsSchedule = $smsScheduleProjection->forCustomer($customer->id);
            $label = $smsSchedule['pending']['scheduled_for_label'] ?? 'next open morning';

            return response()->json([
                'scheduled' => true,
                'scheduled_outbound_message_id' => $row->id,
                'scheduled_for' => $row->scheduled_for?->utc()->toIso8601String(),
                'sms_schedule' => $smsSchedule,
                'message' => "Reply scheduled for {$label}.",
            ]);
        }

        try {
            if ($channel === 'messenger') {
                $messageTag = filled($validated['messenger_message_tag'] ?? null)
                    ? MetaMessengerMessageTag::from((string) $validated['messenger_message_tag'])
                    : null;

                $result = $sendMessenger->execute(
                    customer: $customer,
                    actor: $request->user(),
                    body: (string) ($validated['body'] ?? ''),
                    repairOrder: $repairOrder,
                    messageTag: $messageTag,
                    attachment: $request->file('attachment'),
                );
            } else {
                $cancelPendingSms->forCustomerSmsReply($customer->id, $request->user());

                $result = $sendSms->execute(
                    customer: $customer,
                    actor: $request->user(),
                    body: (string) ($validated['body'] ?? ''),
                    repairOrder: $repairOrder,
                    attachment: $request->file('attachment'),
                );
            }
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $message = $result['message'];

        if (filled($validated['nudge_key'] ?? null) && filled($validated['entity_key'] ?? null)) {
            $recordNudge->execute(
                user: $request->user(),
                entityKey: (string) $validated['entity_key'],
                nudgeKey: (string) $validated['nudge_key'],
                response: AdvisorNudgeResponseKind::Acted,
            );
        }

        return response()->json([
            'message_id' => $message->id,
            'provider_message_sid' => $result['provider_message_sid'] ?? null,
            'provider_message_id' => $result['provider_message_id'] ?? ($result['provider_message_sid'] ?? null),
            'message' => $presenter->present($message),
            'html' => $renderer->render($message, 'border-t border-slate-100'),
            'filter' => $hubCommsTimeline->filterForMessage($message),
            'scheduled' => false,
            'sms_schedule' => $channel === 'sms'
                ? $smsScheduleProjection->forCustomer($customer->id)
                : null,
        ]);
    }
}
