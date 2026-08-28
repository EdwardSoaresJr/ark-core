<?php

namespace App\Ark\Mobile\Push;

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Conversations\CustomerCallContextResolver;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\Telephony\CallSession;
use Illuminate\Support\Str;

/**
 * Aware push copy — customer name as title, operational context as body.
 *
 * Not "New Message." The notification itself should reduce uncertainty.
 */
final class MobileAwarePushCopy
{
    public function __construct(
        private readonly CustomerCallContextResolver $callContextResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $interrupt  CommunicationsMessageQueuePresenter payload
     * @return array{title: string, body: string}
     */
    public function forInboundMessageInterrupt(array $interrupt): array
    {
        $title = trim((string) ($interrupt['headline'] ?? 'Customer'));
        $snippet = trim((string) ($interrupt['snippet'] ?? ''));
        $contextSummary = trim((string) ($interrupt['context_summary'] ?? ''));
        $stateLabel = trim((string) ($interrupt['state_label'] ?? ''));

        if ($snippet !== '') {
            $body = $snippet;
        } elseif ($contextSummary !== '') {
            $body = $contextSummary;
        } elseif ($stateLabel !== '') {
            $body = $stateLabel;
        } else {
            $body = 'Needs a reply';
        }

        if (($interrupt['has_attachment'] ?? false) === true && $snippet === '') {
            $count = (int) ($interrupt['attachment_count'] ?? 1);
            $body = $count > 1 ? "{$count} attachments received" : 'Photo or attachment received';
        }

        return [
            'title' => $title !== '' ? $title : 'Customer',
            'body' => $body,
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    public function forInboundCall(CallSession $session): array
    {
        $session->loadMissing(['customer', 'repairOrder']);

        $phone = trim((string) ($session->from_number ?? $session->to_number ?? ''));
        $customerName = $session->customer?->name;
        $title = filled($customerName)
            ? (string) $customerName
            : (PhoneNumber::display($phone) ?? 'Incoming call');

        $context = $phone !== ''
            ? $this->callContextResolver->resolve($phone)
            : null;

        $repairOrder = $session->repairOrder instanceof RepairOrder
            ? $session->repairOrder
            : $context?->openRepairOrders->first()?->repairOrder;

        $parts = ['Incoming call — open ARK to answer.'];

        if ($repairOrder instanceof RepairOrder) {
            $status = $repairOrder->statusDisplayLabel();
            $vehicle = trim("{$repairOrder->vehicle?->year} {$repairOrder->vehicle?->make} {$repairOrder->vehicle?->model}");

            if ($vehicle !== '') {
                array_unshift($parts, $vehicle);
            }

            if ($status !== '') {
                array_unshift($parts, $status);
            }
        }

        return [
            'title' => $title,
            'body' => Str::limit(implode(' · ', array_filter($parts)), 140),
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    public function forMissedCall(CallSession $session): array
    {
        $session->loadMissing(['customer', 'repairOrder.vehicle']);

        return [
            'title' => $this->customerTitle($session->customer?->name, $session->from_number ?? $session->to_number),
            'body' => $this->withVehicleContext('Missed your call.', $session->repairOrder),
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    public function forVoicemail(CallSession $session): array
    {
        $session->loadMissing(['customer', 'repairOrder.vehicle']);

        return [
            'title' => $this->customerTitle($session->customer?->name, $session->from_number ?? $session->to_number),
            'body' => $this->withVehicleContext('Left a voicemail.', $session->repairOrder),
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    public function forEstimateViewed(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing(['customer', 'vehicle']);

        return [
            'title' => $this->customerTitle($repairOrder->customer?->name),
            'body' => $this->withVehicleContext('Opened the estimate.', $repairOrder),
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    public function forEstimateApproved(ApprovalEvent $approval): array
    {
        $repairOrder = $approval->visit;
        if (! $repairOrder instanceof RepairOrder) {
            return ['title' => 'Customer', 'body' => 'Estimate approved.'];
        }

        $repairOrder->loadMissing(['customer', 'vehicle']);

        $concern = trim((string) ($repairOrder->concern_summary ?? ''));
        $body = $concern !== ''
            ? Str::limit("{$concern} approved.", 140)
            : 'Estimate approved.';

        return [
            'title' => $this->customerTitle($repairOrder->customer?->name),
            'body' => $this->withVehicleContext($body, $repairOrder),
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    public function forPartsArrived(RepairOrder $repairOrder, ?string $partLabel = null): array
    {
        $repairOrder->loadMissing(['customer', 'vehicle']);

        $label = trim((string) ($partLabel ?? ''));
        $body = $label !== ''
            ? Str::limit("{$label} arrived.", 140)
            : 'Parts arrived.';

        return [
            'title' => $this->customerTitle($repairOrder->customer?->name),
            'body' => $this->withVehicleContext($body, $repairOrder),
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    public function forWaitingParts(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing(['customer', 'vehicle']);

        return [
            'title' => $this->customerTitle($repairOrder->customer?->name),
            'body' => $this->withVehicleContext('Waiting on parts.', $repairOrder),
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    public function forVehicleReady(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing(['customer', 'vehicle']);

        return [
            'title' => $this->customerTitle($repairOrder->customer?->name),
            'body' => $this->withVehicleContext('Vehicle ready for pickup.', $repairOrder),
        ];
    }

    private function customerTitle(?string $customerName, ?string $phone = null): string
    {
        $name = trim((string) ($customerName ?? ''));

        if ($name !== '') {
            return $name;
        }

        $display = PhoneNumber::display(trim((string) ($phone ?? '')));

        return $display ?? 'Customer';
    }

    private function withVehicleContext(string $body, ?RepairOrder $repairOrder): string
    {
        if (! $repairOrder instanceof RepairOrder) {
            return Str::limit($body, 140);
        }

        $vehicle = trim("{$repairOrder->vehicle?->year} {$repairOrder->vehicle?->make} {$repairOrder->vehicle?->model}");

        if ($vehicle === '') {
            return Str::limit($body, 140);
        }

        return Str::limit("{$body} · {$vehicle}", 140);
    }

    public function partLabelFromLineId(?int $lineId): ?string
    {
        if ($lineId === null) {
            return null;
        }

        $line = RepairOrderLine::query()->find($lineId);

        if ($line === null) {
            return null;
        }

        $description = trim((string) ($line->description ?? ''));

        return $description !== '' ? $description : null;
    }
}
