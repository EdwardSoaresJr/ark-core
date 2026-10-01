<?php

namespace App\Ark\Platform\Communications;

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\Leads\Lead;
use App\Ark\Operations\Payments\CustomerDocumentAccessToken;
use App\Ark\Operations\Portal\EstimateAccessToken;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;

final class CommunicationMessageContext
{
    /**
     * @return list<array{type: string, id: string}>
     */
    public static function forRepairOrder(RepairOrder $repairOrder): array
    {
        $repairOrder->loadMissing('vehicle');

        $references = [[
            'type' => 'repair_order',
            'id' => (string) $repairOrder->repair_order_id,
        ]];

        if ($repairOrder->vehicle_id) {
            $references[] = [
                'type' => 'vehicle',
                'id' => (string) $repairOrder->vehicle_id,
            ];
        }

        return $references;
    }

    /**
     * @param  list<array{type: string, id: string}>  $base
     * @param  list<array{type?: string, id?: string|int}>  $extra
     * @return list<array{type: string, id: string}>
     */
    public static function merge(array $base, array $extra): array
    {
        $merged = [];
        $seen = [];

        foreach ([...$base, ...$extra] as $reference) {
            $type = strtolower(trim((string) ($reference['type'] ?? '')));
            $id = trim((string) ($reference['id'] ?? ''));
            if ($type === '' || $id === '') {
                continue;
            }

            $key = $type.':'.$id;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $merged[] = [
                'type' => $type,
                'id' => $id,
            ];
        }

        return $merged;
    }

    /**
     * @param  list<array{type: string, id: string}>  $references
     */
    public static function reference(array $references, string $type, int|string $id): array
    {
        return self::merge($references, [[
            'type' => $type,
            'id' => $id,
        ]]);
    }

    public static function label(mixed $references): string
    {
        if (! is_array($references) || $references === []) {
            return '';
        }

        $byType = [];
        foreach ($references as $reference) {
            if (! is_array($reference)) {
                continue;
            }

            $type = (string) ($reference['type'] ?? '');
            $id = (string) ($reference['id'] ?? '');
            if ($type === '' || $id === '') {
                continue;
            }

            $byType[$type] = $id;
        }

        $parts = [];
        foreach (['repair_order', 'vehicle', 'inspection', 'estimate', 'payment_request', 'deposit_request', 'appointment', 'website_lead'] as $type) {
            if (! isset($byType[$type])) {
                continue;
            }

            $label = self::labelFor($type, $byType[$type]);
            if ($label !== '') {
                $parts[] = $label;
            }
        }

        return implode(' · ', $parts);
    }

    private static function labelFor(string $type, string $id): string
    {
        return match ($type) {
            'repair_order' => RepairOrder::query()->where('repair_order_id', $id)->exists()
                ? 'RO '.$id
                : '',
            'vehicle' => self::vehicleLabel($id),
            'inspection' => Inspection::query()->whereKey($id)->exists() ? 'Inspection' : '',
            'estimate' => EstimateAccessToken::query()->whereKey($id)->exists() ? 'Estimate' : '',
            'payment_request' => self::tokenLabel($id, CustomerDocumentAccessToken::SCOPE_PAY_INVOICE, 'Payment request'),
            'deposit_request' => self::tokenLabel($id, CustomerDocumentAccessToken::SCOPE_PAY_DEPOSIT, 'Deposit request'),
            'appointment' => Appointment::query()->whereKey($id)->exists() ? 'Appointment' : '',
            'website_lead' => Lead::query()->whereKey($id)->exists() ? 'Website lead' : '',
            default => '',
        };
    }

    private static function vehicleLabel(string $id): string
    {
        $vehicle = Vehicle::query()->find($id);
        if ($vehicle === null) {
            return '';
        }

        $label = trim(implode(' ', array_filter([
            $vehicle->year,
            $vehicle->make,
            $vehicle->model,
        ], fn (mixed $part): bool => filled($part))));

        return $label !== '' ? $label : 'Vehicle';
    }

    private static function tokenLabel(string $id, string $scope, string $label): string
    {
        $token = CustomerDocumentAccessToken::query()->find($id);

        return $token !== null && $token->scope === $scope ? $label : '';
    }
}
