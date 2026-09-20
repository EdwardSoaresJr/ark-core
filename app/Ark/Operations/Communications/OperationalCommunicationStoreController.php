<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\ConversationMessagePolicy;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationalCommunicationStoreController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        OperationalEventRecorder $events,
        RepairOrderConcurrency $concurrency,
        ConversationRecorder $conversations,
        CommunicationEventRecorder $communicationEvents,
    ): RedirectResponse {
        $repairOrder->ensureOpenForEditing();
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'communication_type' => ['required', Rule::enum(OperationalCommunicationType::class)],
            'channel' => ['required', Rule::enum(OperationalCommunicationChannel::class)],
            'direction' => ['required', Rule::enum(OperationalCommunicationDirection::class)],
            'summary' => ['required', 'string', 'max:1000'],
        ]);

        $eventType = OperationalCommunicationType::from($data['communication_type']);
        $channel = OperationalCommunicationChannel::from($data['channel']);
        $direction = OperationalCommunicationDirection::from($data['direction']);
        $summary = trim($data['summary']);

        $message = null;

        if (ConversationMessagePolicy::recordsFromManualLog($eventType)) {
            $message = $conversations->recordAdvisorLog(
                $repairOrder,
                $request->user(),
                $channel,
                $direction,
                $summary,
            );
        }

        $event = $communicationEvents->record(
            $repairOrder,
            $eventType,
            $channel,
            $direction,
            $summary,
            actor: $request->user(),
            message: $message,
        );

        $events->record(
            OperationalEventName::OperationalCommunicationLogged,
            $repairOrder,
            actor: $request->user(),
            payload: [
                'communication_event_id' => $event->id,
                'communication_type' => $eventType->value,
                'channel' => $channel->value,
                'direction' => $direction->value,
            ],
        );

        return redirect()
            ->back()
            ->with('status', 'Communication logged.');
    }
}
