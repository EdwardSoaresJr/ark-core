<?php

namespace App\Ark\Station;

use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionQueue;
use App\Ark\Operations\Telephony\CallSessionStatus;
use Illuminate\Support\Carbon;

final class StationCallsProjection
{
    public function __construct(
        private readonly CallSessionQueue $queue,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $waiting = $this->queue->waitingSessions();
        $recent = CallSession::query()
            ->with(['customer', 'owner', 'repairOrder.vehicle'])
            ->excludingFeatureCodeDials()
            ->where('started_at', '>=', now()->subHours(24))
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        $items = $recent
            ->map(fn (CallSession $session): array => $this->present($session))
            ->all();

        $active = $waiting
            ->filter(fn (CallSession $session): bool => in_array($session->status, [
                CallSessionStatus::Ringing,
                CallSessionStatus::Answered,
            ], true))
            ->map(fn (CallSession $session): array => $this->present($session))
            ->values()
            ->all();

        $missed = $waiting
            ->filter(fn (CallSession $session): bool => $session->status === CallSessionStatus::Missed && $session->worked_at === null)
            ->map(fn (CallSession $session): array => $this->present($session))
            ->values()
            ->all();

        return [
            'ready' => true,
            'active' => $active,
            'missed' => $missed,
            'recent' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(CallSession $session): array
    {
        $customer = $session->customer;
        $repairOrder = $session->repairOrder;
        $started = $session->started_at instanceof Carbon ? $session->started_at : null;
        $durationSeconds = $session->recording_duration_seconds
            ?? $session->voicemail_duration_seconds
            ?? $this->elapsedSeconds($session);

        return [
            'id' => $session->id,
            'direction' => $session->direction?->value,
            'direction_label' => $session->direction?->queueLabel(),
            'status' => $session->status?->value,
            'status_label' => $session->status?->operationalLabel(),
            'from_display' => $session->from_number,
            'customer_label' => $customer !== null ? trim($customer->first_name.' '.$customer->last_name) : null,
            'owned_by' => $session->owner?->name,
            'repair_order_id' => $repairOrder?->repair_order_id,
            'vehicle_label' => $repairOrder?->vehicle?->display_name,
            'started_at' => $started?->toIso8601String(),
            'started_label' => $started?->timezone(config('app.display_timezone'))->format('g:i A'),
            'duration_label' => $this->durationLabel($durationSeconds),
            'has_voicemail' => $session->hasVoicemail(),
            'needs_callback' => $session->status === CallSessionStatus::Missed && $session->worked_at === null,
        ];
    }

    private function elapsedSeconds(CallSession $session): ?int
    {
        if ($session->answered_at === null || $session->ended_at === null) {
            return null;
        }

        return max(0, $session->ended_at->diffInSeconds($session->answered_at));
    }

    private function durationLabel(?int $seconds): ?string
    {
        if ($seconds === null || $seconds <= 0) {
            return null;
        }

        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $rest);
    }
}
