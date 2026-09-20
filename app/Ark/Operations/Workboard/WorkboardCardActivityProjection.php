<?php

namespace App\Ark\Operations\Workboard;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class WorkboardCardActivityProjection
{
    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @param  array<int, Appointment>|null  $appointmentsByRepairOrderId
     * @return array<int, list<WorkboardCardActivityMark>>
     */
    public function map(Collection $repairOrders, ?array $appointmentsByRepairOrderId = null): array
    {
        if ($repairOrders->isEmpty()) {
            return [];
        }

        $calls = $this->callsByRepairOrder($repairOrders);
        $inspections = $this->inspectionsByRepairOrder($repairOrders);
        $appointments = $appointmentsByRepairOrderId ?? $this->appointmentsFor($repairOrders);
        $strips = [];

        foreach ($repairOrders as $repairOrder) {
            $strips[$repairOrder->id] = $this->marksFor(
                $repairOrder,
                $calls[$repairOrder->id] ?? [],
                $inspections[$repairOrder->id] ?? null,
                $appointments[$repairOrder->id] ?? null,
            );
        }

        return $strips;
    }

    /**
     * @return list<WorkboardCardActivityMark>
     */
    public static function idle(): array
    {
        return [
            self::mark('estimate', 'EST', WorkboardCardActivityState::None, 'Estimate — None'),
            self::mark('sms', 'SMS', WorkboardCardActivityState::None, 'SMS — None'),
            self::mark('email', 'EMAIL', WorkboardCardActivityState::None, 'Email — None'),
            self::mark('phone', 'PHONE', WorkboardCardActivityState::None, 'Phone — None'),
            self::mark('dvi', 'DVI', WorkboardCardActivityState::None, 'DVI — None'),
            self::mark('scheduled', 'APPT', WorkboardCardActivityState::None, 'Scheduled — None'),
        ];
    }

    /**
     * @param  list<CallSession>  $calls
     * @return list<WorkboardCardActivityMark>
     */
    private function marksFor(
        RepairOrder $repairOrder,
        array $calls,
        ?Inspection $inspection,
        ?Appointment $appointment,
    ): array {
        $events = $repairOrder->relationLoaded('communicationEvents')
            ? $repairOrder->communicationEvents
            : collect();

        return [
            $this->estimateMark($repairOrder, $events),
            $this->smsMark($events),
            $this->emailMark($events),
            $this->phoneMark($calls),
            $this->dviMark($events, $inspection),
            $this->scheduledMark($appointment),
        ];
    }

    private function scheduledMark(?Appointment $appointment): WorkboardCardActivityMark
    {
        if (! $appointment instanceof Appointment) {
            return self::mark('scheduled', 'APPT', WorkboardCardActivityState::None, 'Scheduled — None');
        }

        if ($appointment->status === AppointmentStatus::Arrived) {
            return self::mark('scheduled', 'APPT', WorkboardCardActivityState::Engaged, 'Scheduled — Checked in');
        }

        $startsAt = ShopDisplayTimezone::present($appointment->starts_at);
        $now = ShopDisplayTimezone::now();

        if ($startsAt->lt($now)) {
            return self::mark(
                'scheduled',
                'APPT',
                WorkboardCardActivityState::Attention,
                'Scheduled — Missed '.$this->ago($appointment->starts_at),
            );
        }

        $when = $this->scheduledWhenLabel($startsAt, $now);

        if ($startsAt->isSameDay($now) || $appointment->status === AppointmentStatus::Confirmed) {
            return self::mark('scheduled', 'APPT', WorkboardCardActivityState::Sent, 'Scheduled — '.$when);
        }

        return self::mark('scheduled', 'APPT', WorkboardCardActivityState::Ready, 'Scheduled — '.$when);
    }

    private function scheduledWhenLabel(Carbon $startsAt, Carbon $now): string
    {
        if ($startsAt->isSameDay($now)) {
            return 'Today '.$startsAt->format('g:i A');
        }

        if ($startsAt->isSameDay($now->copy()->addDay())) {
            return 'Tomorrow '.$startsAt->format('g:i A');
        }

        return $startsAt->format('D M j, g:i A');
    }

    /**
     * @param  Collection<int, CommunicationEvent>  $events
     */
    private function estimateMark(RepairOrder $repairOrder, Collection $events): WorkboardCardActivityMark
    {
        $viewed = $this->latestOfType($events, OperationalCommunicationType::EstimateViewed);
        $sent = $this->latestOfType($events, OperationalCommunicationType::EstimateSent);

        if ($viewed instanceof CommunicationEvent) {
            return self::mark('estimate', 'EST', WorkboardCardActivityState::Engaged, 'Estimate — Viewed '.$this->ago($viewed->occurred_at));
        }

        if ($sent instanceof CommunicationEvent) {
            return self::mark('estimate', 'EST', WorkboardCardActivityState::Sent, 'Estimate — Sent '.$this->ago($sent->occurred_at));
        }

        if ($this->hasPricedEstimate($repairOrder)) {
            return self::mark('estimate', 'EST', WorkboardCardActivityState::Ready, 'Estimate — Ready');
        }

        return self::mark('estimate', 'EST', WorkboardCardActivityState::None, 'Estimate — None');
    }

    /**
     * @param  Collection<int, CommunicationEvent>  $events
     */
    private function smsMark(Collection $events): WorkboardCardActivityMark
    {
        $sms = $events->filter(fn (CommunicationEvent $event): bool => $this->isSms($event));

        $replied = $this->latestOfType($sms, OperationalCommunicationType::CustomerReply)
            ?? $this->latestInbound($sms);
        $delivered = $this->latestOfType($sms, OperationalCommunicationType::SmsDelivered)
            ?? $this->latestOfType($sms, OperationalCommunicationType::MessageDelivered);
        $failed = $this->latestOfType($sms, OperationalCommunicationType::SmsDeliveryFailed);
        $outbound = $sms
            ->filter(fn (CommunicationEvent $event): bool => $event->direction === OperationalCommunicationDirection::Outbound
                && $event->event_type !== OperationalCommunicationType::SmsDeliveryFailed)
            ->sortByDesc(fn (CommunicationEvent $event) => $event->occurred_at?->timestamp ?? 0)
            ->first();

        if ($replied instanceof CommunicationEvent) {
            return self::mark('sms', 'SMS', WorkboardCardActivityState::Engaged, 'SMS — Customer replied '.$this->ago($replied->occurred_at));
        }

        if ($failed instanceof CommunicationEvent && ! $this->eventIsLater($delivered, $failed)) {
            return self::mark('sms', 'SMS', WorkboardCardActivityState::Attention, 'SMS — Delivery failed '.$this->ago($failed->occurred_at));
        }

        if ($delivered instanceof CommunicationEvent) {
            return self::mark('sms', 'SMS', WorkboardCardActivityState::Sent, 'SMS — Delivered '.$this->ago($delivered->occurred_at));
        }

        if ($outbound instanceof CommunicationEvent) {
            return self::mark('sms', 'SMS', WorkboardCardActivityState::Ready, 'SMS — Sent '.$this->ago($outbound->occurred_at));
        }

        return self::mark('sms', 'SMS', WorkboardCardActivityState::None, 'SMS — None');
    }

    /**
     * @param  Collection<int, CommunicationEvent>  $events
     */
    private function emailMark(Collection $events): WorkboardCardActivityMark
    {
        $email = $events->filter(fn (CommunicationEvent $event): bool => $this->isEmail($event));

        $opened = $this->latestOfType($email, OperationalCommunicationType::MessageRead);
        $sent = $email
            ->filter(fn (CommunicationEvent $event): bool => $event->event_type !== OperationalCommunicationType::MessageRead)
            ->sortByDesc(fn (CommunicationEvent $event) => $event->occurred_at?->timestamp ?? 0)
            ->first();

        if ($opened instanceof CommunicationEvent) {
            return self::mark('email', 'EMAIL', WorkboardCardActivityState::Engaged, 'Email — Opened '.$this->ago($opened->occurred_at));
        }

        if ($sent instanceof CommunicationEvent) {
            return self::mark('email', 'EMAIL', WorkboardCardActivityState::Sent, 'Email — Sent '.$this->ago($sent->occurred_at));
        }

        return self::mark('email', 'EMAIL', WorkboardCardActivityState::None, 'Email — None');
    }

    /**
     * @param  list<CallSession>  $calls
     */
    private function phoneMark(array $calls): WorkboardCardActivityMark
    {
        if ($calls === []) {
            return self::mark('phone', 'PHONE', WorkboardCardActivityState::None, 'Phone — None');
        }

        $attention = collect($calls)->first(
            fn (CallSession $session): bool => $session->worked_at === null
                && ($session->status === CallSessionStatus::Missed || filled($session->voicemail_url)),
        );

        if ($attention instanceof CallSession) {
            $label = filled($attention->voicemail_url) && $attention->status !== CallSessionStatus::Missed
                ? 'Voicemail'
                : 'Missed call';

            return self::mark(
                'phone',
                'PHONE',
                WorkboardCardActivityState::Attention,
                'Phone — '.$label.' '.$this->ago($attention->started_at),
            );
        }

        $latest = $calls[0];
        $when = $this->ago($latest->started_at);
        $count = count($calls);
        $prefix = $count > 1 ? $count.' calls · last ' : '';

        if ($latest->direction === CallSessionDirection::Inbound) {
            return self::mark('phone', 'PHONE', WorkboardCardActivityState::Sent, 'Phone — '.$prefix.'Inbound call '.$when);
        }

        return self::mark('phone', 'PHONE', WorkboardCardActivityState::Sent, 'Phone — '.$prefix.'Outbound call '.$when);
    }

    /**
     * @param  Collection<int, CommunicationEvent>  $events
     */
    private function dviMark(Collection $events, ?Inspection $inspection): WorkboardCardActivityMark
    {
        $sent = $this->latestOfType($events, OperationalCommunicationType::InspectionSent);

        if ($sent instanceof CommunicationEvent) {
            return self::mark('dvi', 'DVI', WorkboardCardActivityState::Sent, 'DVI — Sent '.$this->ago($sent->occurred_at));
        }

        if ($inspection instanceof Inspection) {
            $when = $inspection->completed_at ?? $inspection->started_at;

            return self::mark(
                'dvi',
                'DVI',
                WorkboardCardActivityState::Ready,
                'DVI — On file'.($when instanceof Carbon ? ' '.$this->ago($when) : ''),
            );
        }

        return self::mark('dvi', 'DVI', WorkboardCardActivityState::None, 'DVI — None');
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return array<int, list<CallSession>>
     */
    private function callsByRepairOrder(Collection $repairOrders): array
    {
        $repairOrderIds = $repairOrders->pluck('id')->filter()->values()->all();

        if ($repairOrderIds === []) {
            return [];
        }

        $sessions = CallSession::query()
            ->whereIn('repair_order_id', $repairOrderIds)
            ->where('started_at', '>=', now()->subDays(45))
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'repair_order_id',
                'direction',
                'status',
                'started_at',
                'worked_at',
                'voicemail_url',
            ]);

        $byRepairOrder = [];

        foreach ($sessions as $session) {
            $byRepairOrder[(int) $session->repair_order_id][] = $session;
        }

        $mapped = [];

        foreach ($repairOrders as $repairOrder) {
            $mapped[$repairOrder->id] = $byRepairOrder[$repairOrder->id] ?? [];
        }

        return $mapped;
    }

    /**
     * Next active appointment per board RO.
     * Prefer the appointment linked to this RO. Floor bookings often sit on the vehicle
     * with repair_order_id null (or an old closed RO) — still project onto the open card.
     *
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return array<int, Appointment>
     */
    public function appointmentsFor(Collection $repairOrders): array
    {
        if ($repairOrders->isEmpty()) {
            return [];
        }

        $repairOrderIds = $repairOrders->pluck('id')->filter()->values()->all();
        $vehicleIds = $repairOrders->pluck('vehicle_id')->filter()->unique()->values()->all();

        if ($repairOrderIds === [] && $vehicleIds === []) {
            return [];
        }

        $linked = [];
        $byVehicle = [];

        $appointments = Appointment::query()
            ->whereIn('status', [
                AppointmentStatus::Scheduled,
                AppointmentStatus::Confirmed,
                AppointmentStatus::Arrived,
            ])
            ->where(function ($query) use ($repairOrderIds, $vehicleIds): void {
                if ($repairOrderIds !== []) {
                    $query->whereIn('repair_order_id', $repairOrderIds);
                }

                if ($vehicleIds !== []) {
                    $query->orWhereIn('vehicle_id', $vehicleIds);
                }
            })
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [AppointmentStatus::Arrived->value])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        foreach ($appointments as $appointment) {
            if ($appointment->repair_order_id !== null) {
                $linked[(int) $appointment->repair_order_id] ??= $appointment;
            }

            if ($appointment->vehicle_id !== null) {
                $byVehicle[(int) $appointment->vehicle_id] ??= $appointment;
            }
        }

        $mapped = [];

        foreach ($repairOrders as $repairOrder) {
            $mapped[$repairOrder->id] = $linked[$repairOrder->id]
                ?? ($repairOrder->vehicle_id !== null ? ($byVehicle[(int) $repairOrder->vehicle_id] ?? null) : null);
        }

        return array_filter($mapped);
    }

    /**
     * @param  Collection<int, RepairOrder>  $repairOrders
     * @return array<int, Inspection>
     */
    private function inspectionsByRepairOrder(Collection $repairOrders): array
    {
        $repairOrderIds = $repairOrders->pluck('id')->filter()->values()->all();

        if ($repairOrderIds === []) {
            return [];
        }

        return Inspection::query()
            ->whereIn('repair_order_id', $repairOrderIds)
            ->get(['id', 'repair_order_id', 'started_at', 'completed_at'])
            ->keyBy('repair_order_id')
            ->all();
    }

    /**
     * @param  Collection<int, CommunicationEvent>  $events
     */
    private function latestOfType(Collection $events, OperationalCommunicationType $type): ?CommunicationEvent
    {
        return $events
            ->filter(fn (CommunicationEvent $event): bool => $event->event_type === $type)
            ->sortByDesc(fn (CommunicationEvent $event) => $event->occurred_at?->timestamp ?? 0)
            ->first();
    }

    /**
     * @param  Collection<int, CommunicationEvent>  $events
     */
    private function latestInbound(Collection $events): ?CommunicationEvent
    {
        return $events
            ->filter(fn (CommunicationEvent $event): bool => $event->direction === OperationalCommunicationDirection::Inbound)
            ->sortByDesc(fn (CommunicationEvent $event) => $event->occurred_at?->timestamp ?? 0)
            ->first();
    }

    private function isSms(CommunicationEvent $event): bool
    {
        if ($event->channel === OperationalCommunicationChannel::Sms) {
            return true;
        }

        return in_array($event->event_type, [
            OperationalCommunicationType::SmsDelivered,
            OperationalCommunicationType::SmsDeliveryFailed,
            OperationalCommunicationType::SmsOptOut,
            OperationalCommunicationType::SmsOptIn,
        ], true);
    }

    private function isEmail(CommunicationEvent $event): bool
    {
        return $event->channel === OperationalCommunicationChannel::Email
            || $event->event_type === OperationalCommunicationType::InvoiceSent;
    }

    private function hasPricedEstimate(RepairOrder $repairOrder): bool
    {
        if (! $repairOrder->relationLoaded('lines')) {
            return false;
        }

        return $repairOrder->lines->contains(
            fn ($line): bool => (int) ($line->total_cents ?? 0) > 0 || (int) ($line->unit_price_cents ?? 0) > 0,
        );
    }

    private function eventIsLater(?CommunicationEvent $candidate, CommunicationEvent $than): bool
    {
        if (! $candidate instanceof CommunicationEvent) {
            return false;
        }

        return ($candidate->occurred_at?->timestamp ?? 0) > ($than->occurred_at?->timestamp ?? 0);
    }

    private function ago(?Carbon $at): string
    {
        if (! $at instanceof Carbon) {
            return '';
        }

        return $at->diffForHumans(short: true, parts: 1);
    }

    private static function mark(
        string $key,
        string $shortLabel,
        WorkboardCardActivityState $state,
        string $tooltip,
    ): WorkboardCardActivityMark {
        return new WorkboardCardActivityMark(
            $key,
            $shortLabel,
            $state,
            trim($tooltip),
            self::badgeFor($key, $state),
        );
    }

    private static function badgeFor(string $key, WorkboardCardActivityState $state): ?string
    {
        if ($state === WorkboardCardActivityState::Attention) {
            return 'alert';
        }

        if ($state !== WorkboardCardActivityState::Engaged) {
            return null;
        }

        return match ($key) {
            'estimate', 'email' => 'viewed',
            'sms' => 'replied',
            default => 'check',
        };
    }
}
