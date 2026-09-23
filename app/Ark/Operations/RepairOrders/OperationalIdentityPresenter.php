<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\CustomerFacingEstimateStatus;
use App\Ark\Operations\Encounters\EncounterSource;
use App\Ark\Operations\Financial\FinancialDocumentType;
use App\Ark\Operations\Leads\LeadContactPreference;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Carbon;

final class OperationalIdentityPresenter
{
    /**
     * @return array{
     *     customer: array{title: string, type: string, lines: list<array{label: string, value: string, href: ?string}>},
     *     vehicle: array{title: string, subtitle: ?string, lines: list<array{label: string, value: string}>},
     *     visit: array{title: string, lines: list<array{label: string, value: string}>, posture: ?string},
     * }
     */
    public static function forRepairOrder(
        RepairOrder $repairOrder,
        bool $includeStaffPosture = true,
        bool $customerFacing = false,
        ?string $documentLabel = null,
        ?Carbon $preparedAt = null,
        bool $includeReferral = true,
    ): array {
        $repairOrder->loadMissing(['customer', 'vehicle', 'assignedTechnician', 'encounter.creator']);

        return [
            'customer' => self::customerColumn(
                $repairOrder->customer->name,
                self::customerContactFromCustomer($repairOrder->customer),
                includeReferral: $includeReferral,
            ),
            'vehicle' => self::vehicleColumn(
                $repairOrder->vehicle,
                $repairOrder,
                includeMileage: false,
            ),
            'visit' => self::visitColumn(
                repairOrderId: $repairOrder->repair_order_id,
                statusLabel: $repairOrder->statusDisplayLabel(),
                preparedAt: $preparedAt ?? $repairOrder->opened_at ?? $repairOrder->created_at,
                advisorName: $repairOrder->serviceAdvisorName(),
                technicianLabel: $repairOrder->technicianOwnershipLabel(),
                workflowPosture: $includeStaffPosture
                    ? self::staffWorkflowPosture($repairOrder)
                    : null,
                documentLabel: $documentLabel,
                mileageLines: self::visitMileageLines($repairOrder),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *     customer: array{title: string, type: string, lines: list<array{label: string, value: string, href: ?string}>},
     *     vehicle: array{title: string, subtitle: ?string, lines: list<array{label: string, value: string}>},
     *     visit: array{title: string, lines: list<array{label: string, value: string}>, posture: ?string},
     * }
     */
    public static function fromSnapshot(array $snapshot, bool $customerFacing = true): array
    {
        $vehicle = $snapshot['vehicle'] ?? [];
        $repairOrder = $snapshot['repair_order'] ?? [];
        $customer = $snapshot['customer'] ?? [];
        $generatedBy = self::advisorNameFromSnapshot($snapshot);
        $technicianName = $snapshot['staff']['execution']['technician_name']
            ?? $repairOrder['assigned_technician_name']
            ?? null;

        $preparedAt = isset($repairOrder['created_at'])
            ? Carbon::parse($repairOrder['created_at'])
            : null;

        if ($preparedAt === null && isset($snapshot['generated_at'])) {
            $preparedAt = Carbon::parse($snapshot['generated_at']);
        }

        $documentType = FinancialDocumentType::tryFrom((string) ($snapshot['document_type'] ?? ''))
            ?? FinancialDocumentType::Estimate;
        $documentLabel = filled($snapshot['pdf_document_label'] ?? null)
            ? (string) $snapshot['pdf_document_label']
            : $documentType->label();
        $statusLabel = $documentType === FinancialDocumentType::Estimate
            ? app(CustomerFacingEstimateStatus::class)->labelForSnapshot($snapshot)
            : ($repairOrder['status_label'] ?? 'Estimate');

        return [
            'customer' => self::customerColumn(
                $customer['name'] ?? 'Customer',
                self::customerContactFromSnapshot($customer),
            ),
            'vehicle' => self::vehicleColumnFromSnapshot(
                $vehicle,
                includeMileage: false,
            ),
            'visit' => self::visitColumn(
                repairOrderId: (int) ($repairOrder['repair_order_id'] ?? 0),
                statusLabel: $statusLabel,
                preparedAt: $preparedAt,
                advisorName: $generatedBy,
                technicianLabel: $technicianName,
                workflowPosture: $customerFacing
                    ? null
                    : self::documentWorkflowPosture($snapshot),
                documentLabel: $documentLabel,
                customerDocument: $customerFacing,
                mileageLines: self::customerVisitMileageLines($vehicle),
            ),
        ];
    }

    /**
     * @return array{title: string, lines: list<array{label: string, value: string, href: ?string}>}
     */
    public static function customerIdentity(Customer $customer): array
    {
        return self::customerColumn($customer->name, self::customerContactFromCustomer($customer));
    }

    /**
     * @return array{title: string, subtitle: ?string, lines: list<array{label: string, value: string}>}
     */
    public static function vehicleIdentity(Vehicle $vehicle, ?RepairOrder $repairOrder = null): array
    {
        return self::vehicleColumn($vehicle, $repairOrder);
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array{title: string, type: string, lines: list<array{label: string, value: string, href: ?string, secondary_value?: ?string}>}
     */
    private static function customerColumn(string $name, array $contact, bool $includeReferral = true): array
    {
        $customerType = filled($contact['customer_type'] ?? null)
            ? (string) $contact['customer_type']
            : 'Retail';

        $lines = [];

        $reachVia = self::reachViaLine($contact['contact_preference'] ?? null);

        if ($reachVia !== null) {
            $lines[] = $reachVia;
        }

        if (filled($contact['email'] ?? null)) {
            $lines[] = [
                'label' => 'Email',
                'value' => $contact['email'],
                'href' => 'mailto:'.$contact['email'],
            ];
        }

        if (filled($contact['phone'] ?? null)) {
            $lines[] = [
                'label' => 'Phone',
                'value' => PhoneNumber::display($contact['phone']) ?? $contact['phone'],
                'href' => PhoneNumber::telUri($contact['phone']),
            ];
        }

        if ($includeReferral && filled($contact['referral_source'] ?? null)) {
            $referral = EncounterSource::tryFrom((string) $contact['referral_source']);

            $lines[] = [
                'label' => 'Referral',
                'value' => $referral?->label() ?? (string) $contact['referral_source'],
                'href' => null,
            ];
        }

        $addressLines = self::customerAddressLines($contact);

        if ($addressLines !== null) {
            $lines[] = [
                'label' => 'Address',
                'value' => $addressLines['street'] ?? $addressLines['locality'] ?? '-',
                'secondary_value' => filled($addressLines['street'] ?? null) ? $addressLines['locality'] : null,
                'href' => null,
            ];
        }

        return [
            'title' => $name,
            'type' => $customerType,
            'lines' => $lines,
        ];
    }

    /**
     * @return array{title: string, subtitle: ?string, lines: list<array{label: string, value: string}>}
     */
    private static function vehicleColumn(
        Vehicle $vehicle,
        ?RepairOrder $repairOrder = null,
        bool $compactMileage = false,
        bool $includeMileage = true,
    ): array {
        return self::vehicleColumnFromSnapshot([
            'display_name' => $vehicle->display_name,
            'nickname' => $vehicle->nickname,
            'vin' => $vehicle->authoritativeVin(),
            'plate' => $vehicle->plate,
            'plate_state' => $vehicle->plate_state,
            'mileage_in' => $repairOrder?->resolvedMileageIn(),
            'mileage_out' => $repairOrder?->resolvedMileageOut(),
            'mileage' => $vehicle->legacyOdometerReading(),
            'color' => $vehicle->color,
            'engine' => $vehicle->engine_display ?: $vehicle->engine,
        ], $compactMileage, $includeMileage);
    }

    /**
     * @param  array<string, mixed>  $vehicle
     * @return array{title: string, subtitle: ?string, lines: list<array{label: string, value: string}>}
     */
    private static function vehicleColumnFromSnapshot(
        array $vehicle,
        bool $compactMileage = false,
        bool $includeMileage = true,
    ): array {
        $lines = [];

        if (filled($vehicle['vin'] ?? null) || filled($vehicle['normalized_vin'] ?? null)) {
            $lines[] = [
                'label' => 'VIN',
                'value' => (string) ($vehicle['vin'] ?? $vehicle['normalized_vin']),
            ];
        }

        $plateLine = self::plateLine($vehicle['plate'] ?? null, $vehicle['plate_state'] ?? null);
        if ($plateLine !== null) {
            $lines[] = ['label' => 'Plate', 'value' => $plateLine];
        }

        if ($includeMileage) {
            foreach (self::mileageLines($vehicle, $compactMileage) as $mileageLine) {
                $lines[] = $mileageLine;
            }
        }

        return [
            'title' => self::vehicleTitleFromSnapshot($vehicle),
            'subtitle' => self::vehicleDescriptorSubtitle(
                $vehicle['color'] ?? null,
                $vehicle['engine_display'] ?? $vehicle['engine'] ?? null,
                $vehicle['nickname'] ?? null,
                $vehicle['display_name'] ?? null,
            ),
            'lines' => $lines,
        ];
    }

    /**
     * @param  array<string, mixed>  $vehicle
     */
    private static function vehicleTitleFromSnapshot(array $vehicle): string
    {
        $nickname = filled($vehicle['nickname'] ?? null) ? trim((string) $vehicle['nickname']) : null;
        $displayName = trim((string) ($vehicle['display_name'] ?? 'Vehicle'));

        if ($nickname !== null && $nickname !== '') {
            return $nickname;
        }

        return $displayName !== '' ? $displayName : 'Vehicle';
    }

    private static function vehicleDescriptorSubtitle(
        ?string $color,
        ?string $engine,
        ?string $nickname = null,
        ?string $displayName = null,
    ): ?string {
        $parts = [];

        if (filled($nickname) && filled($displayName) && trim($nickname) !== trim($displayName)) {
            $parts[] = trim($displayName);
        }

        if (filled($color)) {
            $parts[] = (string) $color;
        }

        if (filled($engine)) {
            $parts[] = (string) $engine;
        }

        if ($parts === []) {
            return null;
        }

        return implode(' · ', $parts);
    }

    /**
     * @return array{title: string, lines: list<array{label: string, value: string}>, posture: ?string, status_badge: ?string}
     */
    private static function visitColumn(
        int $repairOrderId,
        string $statusLabel,
        ?Carbon $preparedAt,
        ?string $advisorName,
        ?string $technicianLabel,
        ?string $workflowPosture,
        ?string $documentLabel,
        bool $customerDocument = false,
        array $mileageLines = [],
    ): array {
        $lines = [];

        if (! $customerDocument && $documentLabel === null) {
            $lines[] = ['label' => 'Status', 'value' => $statusLabel];
        }

        foreach ($mileageLines as $mileageLine) {
            $lines[] = $mileageLine;
        }

        if ($preparedAt !== null) {
            $lines[] = [
                'label' => $documentLabel === null ? 'Opened' : 'Prepared',
                'value' => $preparedAt->timezone(config('app.display_timezone'))->format('M j, Y g:i A'),
            ];
        }

        if (filled($advisorName)) {
            $lines[] = ['label' => 'Advisor', 'value' => $advisorName];
        }

        if (! $customerDocument || ! self::isPlaceholderTechnician($technicianLabel)) {
            $lines[] = [
                'label' => 'Technician',
                'value' => self::technicianDisplayLabel($technicianLabel),
            ];
        }

        $title = $customerDocument
            ? 'RO #'.$repairOrderId
            : ($documentLabel === null
                ? 'RO #'.$repairOrderId
                : $documentLabel.' · RO #'.$repairOrderId);

        return [
            'title' => $title,
            'lines' => $lines,
            'posture' => $workflowPosture,
            'status_badge' => $customerDocument ? $statusLabel : null,
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private static function visitMileageLines(RepairOrder $repairOrder): array
    {
        return self::customerVisitMileageLines([
            'mileage_in' => $repairOrder->resolvedMileageIn(),
            'mileage_out' => $repairOrder->resolvedMileageOut(),
            'mileage' => $repairOrder->vehicle?->legacyOdometerReading(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $vehicle
     * @return list<array{label: string, value: string}>
     */
    private static function customerVisitMileageLines(array $vehicle): array
    {
        $mileageIn = self::numericMileage($vehicle['mileage_in'] ?? $vehicle['mileage'] ?? $vehicle['mileage_display'] ?? null);
        $mileageOut = self::numericMileage($vehicle['mileage_out'] ?? null);

        if ($mileageIn === null && $mileageOut === null) {
            return [];
        }

        if ($mileageOut === null || $mileageIn === $mileageOut) {
            return [[
                'label' => 'Mileage',
                'value' => self::formatMileage($mileageIn ?? $mileageOut),
            ]];
        }

        if ($mileageIn === null) {
            return [[
                'label' => 'Mileage',
                'value' => self::formatMileage($mileageOut),
            ]];
        }

        return [
            ['label' => 'Mileage in', 'value' => self::formatMileage($mileageIn)],
            ['label' => 'Mileage out', 'value' => self::formatMileage($mileageOut)],
        ];
    }

    private static function mileageLines(array $vehicle, bool $compact = false): array
    {
        $mileageIn = $vehicle['mileage_in'] ?? $vehicle['mileage'] ?? $vehicle['mileage_display'] ?? null;
        $mileageOut = $vehicle['mileage_out'] ?? null;

        if ($compact) {
            return [[
                'label' => 'Mileage',
                'value' => self::formatMileageDisplay($mileageIn).' / '.self::formatMileageDisplay($mileageOut),
            ]];
        }

        return [
            [
                'label' => 'Mileage in',
                'value' => self::formatMileageDisplay($mileageIn),
            ],
            [
                'label' => 'Mileage out',
                'value' => self::formatMileageDisplay($mileageOut),
            ],
        ];
    }

    private static function numericMileage(mixed $mileage): ?int
    {
        if ($mileage === null || $mileage === '') {
            return null;
        }

        return is_numeric($mileage) ? (int) $mileage : null;
    }

    private static function formatMileageDisplay(mixed $mileage): string
    {
        if ($mileage === null || $mileage === '') {
            return '-';
        }

        return self::formatMileage($mileage);
    }

    private static function formatMileage(mixed $mileage): string
    {
        return is_numeric($mileage) ? number_format((int) $mileage) : (string) $mileage;
    }

    private static function plateLine(?string $plate, ?string $state): ?string
    {
        $plate = trim((string) $plate);
        $state = trim((string) $state);

        if ($plate === '' && $state === '') {
            return null;
        }

        if ($plate !== '' && $state !== '') {
            return $plate.' / '.$state;
        }

        return $plate !== '' ? $plate : $state;
    }

    /**
     * @return array{phone: ?string, email: ?string, contact_preference: ?string, address_line_1: ?string, address_line_2: ?string, city: ?string, state: ?string, postal_code: ?string, customer_type: ?string}
     */
    private static function customerContactFromCustomer(Customer $customer): array
    {
        return [
            'phone' => $customer->phone,
            'email' => $customer->email,
            'contact_preference' => $customer->contact_preference?->value,
            'address_line_1' => $customer->address_line_1,
            'address_line_2' => $customer->address_line_2,
            'city' => $customer->city,
            'state' => $customer->state,
            'postal_code' => $customer->postal_code,
            'customer_type' => $customer->customer_type,
            'referral_source' => $customer->referral_source,
        ];
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return array{phone: ?string, email: ?string, contact_preference: ?string, address_line_1: ?string, address_line_2: ?string, city: ?string, state: ?string, postal_code: ?string, display_address?: ?string, customer_type: ?string}
     */
    private static function customerContactFromSnapshot(array $customer): array
    {
        return [
            'phone' => $customer['phone'] ?? null,
            'email' => $customer['email'] ?? null,
            'contact_preference' => $customer['contact_preference'] ?? null,
            'address_line_1' => $customer['address_line_1'] ?? null,
            'address_line_2' => $customer['address_line_2'] ?? null,
            'city' => $customer['city'] ?? null,
            'state' => $customer['state'] ?? null,
            'postal_code' => $customer['postal_code'] ?? null,
            'display_address' => $customer['display_address'] ?? null,
            'customer_type' => $customer['customer_type'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array{street: ?string, locality: ?string}|null
     */
    private static function customerAddressLines(array $contact): ?array
    {
        $street = trim(implode(' ', array_filter([
            $contact['address_line_1'] ?? null,
            $contact['address_line_2'] ?? null,
        ])));

        $locality = trim(implode(', ', array_filter([
            $contact['city'] ?? null,
            $contact['state'] ?? null,
        ])));

        if (filled($contact['postal_code'] ?? null)) {
            $locality = trim($locality.' '.$contact['postal_code']);
        }

        if ($street === '' && $locality === '' && filled($contact['display_address'] ?? null)) {
            $parts = array_map(trim(...), explode('·', (string) $contact['display_address'], 2));
            $street = $parts[0] ?? '';
            $locality = $parts[1] ?? '';
        }

        if ($street === '' && $locality === '') {
            return null;
        }

        return [
            'street' => $street !== '' ? $street : null,
            'locality' => $locality !== '' ? $locality : null,
        ];
    }

    /**
     * @return array{label: string, value: string, href: null}|null
     */
    private static function reachViaLine(mixed $contactPreference): ?array
    {
        if (! filled($contactPreference)) {
            return null;
        }

        $preference = $contactPreference instanceof LeadContactPreference
            ? $contactPreference
            : LeadContactPreference::tryFrom((string) $contactPreference);

        if ($preference === null) {
            return null;
        }

        return [
            'label' => 'Reach via',
            'value' => $preference->outreachLabel(),
            'href' => null,
        ];
    }

    private static function technicianDisplayLabel(?string $technicianLabel): string
    {
        return self::isPlaceholderTechnician($technicianLabel) ? 'Unassigned' : (string) $technicianLabel;
    }

    private static function isPlaceholderTechnician(?string $technicianLabel): bool
    {
        return in_array($technicianLabel, [
            null,
            '',
            'Unassigned',
            'Unassigned tech',
            'Unassigned technician',
            'Needs owner',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function advisorNameFromSnapshot(array $snapshot): ?string
    {
        $generatedBy = $snapshot['generated_by']['name'] ?? null;

        if (filled($generatedBy)) {
            return (string) $generatedBy;
        }

        $advisorName = $snapshot['repair_order']['advisor_name'] ?? null;

        return filled($advisorName) ? (string) $advisorName : null;
    }

    private static function staffWorkflowPosture(RepairOrder $repairOrder): ?string
    {
        return match (true) {
            $repairOrder->status->is(RepairOrderStatus::WaitingApproval) => 'Awaiting customer approval',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function documentWorkflowPosture(array $snapshot): ?string
    {
        $concerns = collect($snapshot['concerns'] ?? []);
        $approvedCount = $concerns->where('disposition', 'approved')->count();
        $deferredCount = $concerns->where('disposition', 'deferred')->count()
            + $concerns->where('disposition', 'declined')->count();
        $recommendedCount = $concerns->where('disposition', 'recommended')->count();
        $roStatus = $snapshot['repair_order']['status'] ?? null;

        return match (true) {
            $roStatus === 'waiting_approval' || $roStatus === 'awaiting_approval' => 'Awaiting customer approval',
            $recommendedCount > 0 && $approvedCount === 0 && $deferredCount === 0 => null,
            $approvedCount > 0 => 'Approved work documented',
            default => null,
        };
    }
}
