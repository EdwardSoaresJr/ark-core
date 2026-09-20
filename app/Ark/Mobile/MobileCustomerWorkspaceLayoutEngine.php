<?php

namespace App\Ark\Mobile;

use App\Ark\Operations\Observations\OperationalObservationType;
use App\Ark\Operations\Conversations\CustomerCallContextOpenRepairOrder;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Customer workspace orientation — consumes operational observations, not raw authority meaning.
 *
 * Authority payload (customer, RO, timeline rows) is loaded for display blocks only.
 * Layout/emphasis follows the selected observation type.
 */
final class MobileCustomerWorkspaceLayoutEngine
{
    private const TIMELINE_LIMIT = 20;

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function compose(?OperationalObservationType $observation, array $context, bool $inferred): array
    {
        $orientation = $this->orientationForObservation($observation, $context);
        $blocks = $this->blocksForObservation($observation, $context);

        return [
            'observation' => $this->presentObservation($observation, $orientation, $inferred),
            'orientation' => $orientation,
            'blocks' => $blocks,
        ];
    }

    /**
     * @param  array<string, mixed>  $orientation
     * @return array<string, mixed>
     */
    private function presentObservation(?OperationalObservationType $observation, array $orientation, bool $inferred): array
    {
        if ($observation === null) {
            return [
                'key' => 'browse',
                'label' => 'Customer',
                'headline' => (string) ($orientation['current_situation'] ?? 'Customer'),
                'inferred' => $inferred,
            ];
        }

        return [
            'key' => $observation->value,
            'label' => $observation->label(),
            'headline' => (string) ($orientation['current_situation'] ?? $observation->label()),
            'inferred' => $inferred,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function orientationForObservation(?OperationalObservationType $observation, array $context): array
    {
        /** @var Customer $customer */
        $customer = $context['customer'];
        /** @var User $viewer */
        $viewer = $context['viewer'];
        $firstName = trim((string) $customer->first_name);
        $namePrefix = $firstName !== '' ? $firstName : 'customer';

        /** @var OperationalEventEntry|null $latest */
        $latest = $context['timeline']->first();
        /** @var CustomerCallContextOpenRepairOrder|null $primaryOpen */
        $primaryOpen = $context['primary_open'];
        /** @var RepairOrder|null $primaryRo */
        $primaryRo = $context['primary_repair_order'];

        return match ($observation) {
            OperationalObservationType::CustomerReplied => [
                'current_situation' => $this->messageSituation($latest, $namePrefix),
                'next_best_action' => $this->replyAction($customer, $viewer, $context),
            ],
            OperationalObservationType::IncomingCall => [
                'current_situation' => $this->callSituation($latest, $context['call_preview']),
                'next_best_action' => $this->callAction(
                    $customer,
                    $primaryRo,
                    (string) ($context['dial_method'] ?? 'native'),
                ),
            ],
            OperationalObservationType::AppointmentUpcoming, OperationalObservationType::CustomerArrived => [
                'current_situation' => 'Schedule from the phone or open today\'s appointments',
                'next_best_action' => $this->checkInAction($customer, $viewer, (bool) ($context['can_intake'] ?? false)),
            ],
            OperationalObservationType::PaymentReceived, OperationalObservationType::EstimateViewed => [
                'current_situation' => $this->paymentSituation($latest),
                'next_best_action' => $this->reviewPaymentAction($customer, $primaryRo),
            ],
            OperationalObservationType::RepairOrderWaiting => [
                'current_situation' => $this->repairOrderSituation($primaryOpen, $primaryRo),
                'next_best_action' => $this->openRepairOrderAction($primaryRo),
            ],
            OperationalObservationType::WarrantyApproved,
            OperationalObservationType::PartsArrived,
            OperationalObservationType::VehicleReady,
            OperationalObservationType::InternalRequest,
            null => $context['default_orientation'],
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    private function blocksForObservation(?OperationalObservationType $observation, array $context): array
    {
        $conversation = $this->block('conversation', 'Latest Conversation', 'primary', $context['conversation_payload']);
        $calls = $this->block('calls', 'Recent Calls', 'primary', $context['calls_payload']);
        $repairOrder = $this->block('repair_order', 'Open Repair Order', 'secondary', $context['repair_order_payload']);
        $openWork = $this->block('open_work', 'Open Work', 'primary', $context['open_work_payload']);
        $vehicle = $this->block('vehicle', 'Vehicle', 'secondary', $context['vehicle_payload']);
        $appointment = $this->block('appointment', 'Appointment', 'primary', $context['appointment_payload']);
        $timeline = $this->block('timeline', 'Customer Timeline', 'secondary', $context['timeline_payload']);

        return match ($observation) {
            OperationalObservationType::CustomerReplied => array_values(array_filter([
                $conversation,
                $openWork ?? $repairOrder,
                $vehicle,
                $timeline,
            ])),
            OperationalObservationType::IncomingCall => array_values(array_filter([
                $calls,
                $openWork ?? $repairOrder,
                $vehicle,
                $timeline,
            ])),
            OperationalObservationType::AppointmentUpcoming, OperationalObservationType::CustomerArrived => array_values(array_filter([
                $appointment,
                $vehicle,
                $openWork ?? $repairOrder,
                $timeline,
            ])),
            OperationalObservationType::PaymentReceived, OperationalObservationType::EstimateViewed => array_values(array_filter([
                $timeline,
                $openWork ?? $repairOrder,
                $vehicle,
            ])),
            OperationalObservationType::RepairOrderWaiting => array_values(array_filter([
                $openWork ?? $repairOrder,
                $vehicle,
                $conversation,
                $timeline,
            ])),
            OperationalObservationType::WarrantyApproved,
            OperationalObservationType::PartsArrived,
            OperationalObservationType::VehicleReady,
            OperationalObservationType::InternalRequest,
            null => array_values(array_filter([
                $conversation,
                $calls,
                $openWork ?? $repairOrder,
                $vehicle,
                $timeline,
            ])),
        };
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function block(string $key, string $label, string $emphasis, ?array $payload): ?array
    {
        if ($payload === null || ($payload['enabled'] ?? true) === false) {
            return null;
        }

        return [
            'key' => $key,
            'label' => $label,
            'emphasis' => $emphasis,
            'payload' => $payload,
        ];
    }

    private function messageSituation(?OperationalEventEntry $latest, string $namePrefix): string
    {
        if ($latest !== null && $latest->kind === OperationalEventKind::Sms) {
            $age = $latest->occurredAt->diffForHumans(short: true);

            return "Customer replied {$age}";
        }

        return "Waiting on {$namePrefix}";
    }

    /**
     * @param  list<array<string, mixed>>  $callPreview
     */
    private function callSituation(?OperationalEventEntry $latest, array $callPreview): string
    {
        if ($latest !== null && in_array($latest->kind, [
            OperationalEventKind::MissedCall,
            OperationalEventKind::Voicemail,
            OperationalEventKind::Call,
        ], true)) {
            $age = $latest->occurredAt->diffForHumans(short: true);

            return match ($latest->kind) {
                OperationalEventKind::MissedCall => "Missed call {$age}",
                OperationalEventKind::Voicemail => "Voicemail {$age}",
                default => "Call activity {$age}",
            };
        }

        if ($callPreview !== []) {
            return (string) ($callPreview[0]['headline'] ?? 'Recent call activity');
        }

        return 'No recent calls';
    }

    private function paymentSituation(?OperationalEventEntry $latest): string
    {
        if ($latest === null) {
            return 'No recent payment activity';
        }

        $age = $latest->occurredAt->diffForHumans(short: true);

        return "{$latest->headline} · {$age}";
    }

    private function repairOrderSituation(
        ?CustomerCallContextOpenRepairOrder $primaryOpen,
        ?RepairOrder $primaryRo,
    ): string {
        if ($primaryOpen !== null) {
            $orientation = is_array($primaryOpen->orientation ?? null) ? $primaryOpen->orientation : [];

            return (string) ($orientation['situation'] ?? $primaryOpen->workflowPostureLabel);
        }

        if ($primaryRo !== null) {
            return $primaryRo->communicationPostureLabel();
        }

        return 'No open repair order';
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function replyAction(Customer $customer, User $viewer, array $context): array
    {
        $firstName = trim((string) $customer->first_name);
        $label = $firstName !== '' ? "Reply to {$firstName}" : 'Reply to customer';

        return [
            'label' => $label,
            'key' => 'reply',
            'enabled' => (bool) ($context['can_reply'] ?? false),
            'params' => array_filter([
                'conversation_id' => $context['conversation_id'] ?? null,
                'customer_id' => $customer->id,
            ]),
        ];
    }

    private function callAction(Customer $customer, ?RepairOrder $primaryRo, string $dialMethod): array
    {
        $firstName = trim((string) $customer->first_name);
        $label = $firstName !== '' ? "Call {$firstName}" : 'Call customer';

        return [
            'label' => $label,
            'key' => 'call',
            'enabled' => filled($customer->phone),
            'params' => array_filter([
                'phone' => $customer->phone,
                'customer_id' => $customer->id,
                'repair_order_id' => $primaryRo?->repair_order_id,
                'dial_method' => $dialMethod,
            ]),
        ];
    }

    private function checkInAction(Customer $customer, User $viewer, bool $canIntake): array
    {
        return [
            'label' => 'Check In customer',
            'key' => 'check_in',
            'enabled' => $canIntake,
            'params' => [
                'customer_id' => $customer->id,
            ],
        ];
    }

    private function reviewPaymentAction(Customer $customer, ?RepairOrder $primaryRo): array
    {
        return [
            'label' => $primaryRo !== null ? 'Open repair order' : 'Review customer timeline',
            'key' => $primaryRo !== null ? 'open_repair_order' : 'review_timeline',
            'enabled' => true,
            'params' => array_filter([
                'repair_order_id' => $primaryRo?->repair_order_id,
                'customer_id' => $customer->id,
            ]),
        ];
    }

    private function openRepairOrderAction(?RepairOrder $primaryRo): array
    {
        return [
            'label' => $primaryRo !== null ? 'Open repair order' : 'No open repair order',
            'key' => 'open_repair_order',
            'enabled' => $primaryRo !== null,
            'params' => array_filter([
                'repair_order_id' => $primaryRo?->repair_order_id,
            ]),
        ];
    }

    /**
     * @param  Collection<int, OperationalEventEntry>  $timeline
     * @return list<array<string, mixed>>
     */
    public function presentTimeline(Collection $timeline, int $limit = self::TIMELINE_LIMIT): array
    {
        return $timeline
            ->take($limit)
            ->map(fn (OperationalEventEntry $row): array => [
                'kind' => $row->kind->value,
                'headline' => $row->headline,
                'body' => $row->body,
                'occurred_at' => $row->occurredAt->toIso8601String(),
                'age_label' => $row->occurredAt->diffForHumans(),
                'hub_filter' => $row->hubFilter(),
                'tone' => $row->tone->value,
                'call_session_id' => $row->metadata['call_session_id'] ?? null,
                'conversation_id' => $row->metadata['conversation_id'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, OperationalEventEntry>  $timeline
     * @return list<array<string, mixed>>
     */
    public function callPreview(Collection $timeline, int $limit = 5): array
    {
        return $timeline
            ->filter(fn (OperationalEventEntry $row): bool => $row->hubFilter() === 'call')
            ->take($limit)
            ->map(fn (OperationalEventEntry $row): array => [
                'kind' => $row->kind->value,
                'headline' => $row->headline,
                'body' => $row->body,
                'age_label' => $row->occurredAt->diffForHumans(),
                'call_session_id' => $row->metadata['call_session_id'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, OperationalEventEntry>  $timeline
     * @return list<array<string, mixed>>
     */
    public function messagePreview(Collection $timeline, int $limit = 5): array
    {
        return $timeline
            ->filter(fn (OperationalEventEntry $row): bool => $row->hubFilter() === 'text')
            ->take($limit)
            ->map(fn (OperationalEventEntry $row): array => [
                'kind' => $row->kind->value,
                'headline' => $row->headline,
                'body' => $row->body,
                'age_label' => $row->occurredAt->diffForHumans(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $money
     * @return array<string, mixed>|null
     */
    public function repairOrderPayload(?RepairOrder $repairOrder, ?array $money = null): ?array
    {
        if ($repairOrder === null) {
            return null;
        }

        return [
            'enabled' => true,
            'repair_order_id' => $repairOrder->repair_order_id,
            'number_label' => (string) $repairOrder->repair_order_id,
            'status_label' => $repairOrder->status->label(),
            'vehicle_label' => $repairOrder->vehicle?->display_name ?? 'Vehicle',
            'concern_summary' => $repairOrder->concern_summary,
            'estimate_total_label' => $money['estimate_total_label'] ?? null,
            'balance_due_label' => $money['balance_due_label'] ?? null,
            'balance_due_outstanding' => (bool) ($money['balance_due_outstanding'] ?? false),
        ];
    }

    /**
     * @param  Collection<int, RepairOrder>  $openRepairOrders
     * @param  callable(RepairOrder): (?array<string, mixed>)  $moneyFor
     * @return array<string, mixed>|null
     */
    public function openWorkPayload(Collection $openRepairOrders, callable $moneyFor): ?array
    {
        if ($openRepairOrders->count() < 2) {
            return null;
        }

        $items = $openRepairOrders
            ->map(function (RepairOrder $repairOrder) use ($moneyFor): array {
                $money = $moneyFor($repairOrder);

                return $this->repairOrderPayload($repairOrder, $money) ?? [];
            })
            ->filter(fn (array $item): bool => $item !== [])
            ->values()
            ->all();

        if ($items === []) {
            return null;
        }

        return [
            'enabled' => true,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function vehiclePayload(?Vehicle $vehicle, ?string $fallbackLabel): ?array
    {
        $label = $vehicle !== null
            ? trim((string) ($vehicle->display_name ?? ''))
            : trim((string) ($fallbackLabel ?? ''));

        if ($label === '') {
            return null;
        }

        return [
            'enabled' => true,
            'label' => $label,
            'plate' => $vehicle?->plate,
            'vehicle_id' => $vehicle?->id,
        ];
    }
}
