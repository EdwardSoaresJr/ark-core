<?php

namespace App\Ark\Import;

use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderPaymentStatus;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\VehicleDrivetrainNormalizer;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Str;

final class LegacyArkSmsValueMapper
{
    public function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return substr($digits, 0, 11);
        }

        if (strlen($digits) === 10) {
            return $digits;
        }

        return Str::limit($digits, 32, '');
    }

    public function normalizeEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        $email = trim(strtolower($email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function normalizeVin(?string $vin, LegacyImportReport $report, string $context): ?string
    {
        if ($vin === null || trim($vin) === '') {
            return null;
        }

        $vin = strtoupper(trim($vin));

        if (strlen($vin) > 17) {
            $report->addWarning("{$context}: VIN longer than 17 chars; stored as null.");

            return null;
        }

        return $vin;
    }

    public function dollarsToCents(mixed $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return BigDecimal::of((string) $amount)
            ->multipliedBy(100)
            ->toScale(0, RoundingMode::HALF_UP)
            ->toInt();
    }

    public function mapCustomerType(?string $type): string
    {
        $trimmed = trim((string) $type);

        if ($trimmed === '') {
            return 'Retail';
        }

        $normalized = Str::lower($trimmed);

        return match ($normalized) {
            'repairpal', 'repair pal' => 'Warranty',
            'military' => 'Military',
            'fleet' => 'Fleet',
            'business' => 'Business',
            'commercial', 'wholesale' => 'Commercial',
            'retail' => 'Retail',
            default => $trimmed,
        };
    }

    public function mapLineType(?string $type, LegacyImportReport $report, string $context): RepairOrderLineType
    {
        $normalized = Str::lower(trim((string) $type));

        $mapped = match ($normalized) {
            'labor', 'labour' => RepairOrderLineType::Labor,
            'part', 'parts' => RepairOrderLineType::Part,
            'fee', 'shop_supplies', 'supplies' => RepairOrderLineType::Fee,
            'note', 'notes', 'text' => RepairOrderLineType::Note,
            'sublet', 'service' => RepairOrderLineType::Sublet,
            'package' => RepairOrderLineType::Package,
            default => null,
        };

        if ($mapped === null) {
            $report->addWarning("{$context}: unknown line type '{$type}'; imported as note.");

            return RepairOrderLineType::Note;
        }

        return $mapped;
    }

    public function mapDisposition(?string $disposition): RepairOrderConcernDisposition
    {
        $normalized = Str::lower(trim((string) $disposition));

        return match ($normalized) {
            'approved', 'authorized', 'paid' => RepairOrderConcernDisposition::Approved,
            'deferred', 'deferred_maintenance' => RepairOrderConcernDisposition::Deferred,
            'declined', 'rejected' => RepairOrderConcernDisposition::Declined,
            'published' => RepairOrderConcernDisposition::Approved,
            'draft', '' => RepairOrderConcernDisposition::Draft,
            default => RepairOrderConcernDisposition::Recommended,
        };
    }

    public function mapPaymentStatus(mixed $legacyPayment, mixed $legacyPaidFlag): RepairOrderPaymentStatus
    {
        $normalized = Str::lower(trim((string) $legacyPayment));

        if (in_array($normalized, ['paid', 'paid_in_full'], true)
            || $legacyPaidFlag === 1
            || $legacyPaidFlag === true
            || $legacyPaidFlag === '1'
            || (is_string($legacyPaidFlag) && trim($legacyPaidFlag) !== '' && trim($legacyPaidFlag) !== '0')) {
            return RepairOrderPaymentStatus::Paid;
        }

        return RepairOrderPaymentStatus::Unpaid;
    }

    public function mapRepairOrderStatus(
        ?string $legacyStatus,
        LegacyImportReport $report,
    ): RepairOrderStatus {
        $raw = Str::lower(trim((string) $legacyStatus));
        $mapped = config('legacy-arksms-import.status_map')[$raw] ?? null;

        if ($mapped === null) {
            if ($raw !== '') {
                $report->unmappedStatuses[] = $legacyStatus;
            }

            return RepairOrderStatus::Closed;
        }

        return RepairOrderStatus::from($mapped);
    }

    public function appendMileageNote(?string $existingNotes, mixed $mileage): ?string
    {
        if ($mileage === null || $mileage === '') {
            return $existingNotes;
        }

        $snippet = 'Legacy odometer: '.(int) $mileage;

        if ($existingNotes !== null && str_contains($existingNotes, $snippet)) {
            return $existingNotes;
        }

        return trim(($existingNotes ?? '')."\n".$snippet);
    }

    public function normalizeDrive(?string $drive): ?string
    {
        return VehicleDrivetrainNormalizer::normalizeDrive($drive);
    }

    public function normalizeTransmission(?string $transmission): ?string
    {
        return VehicleDrivetrainNormalizer::normalizeTransmission($transmission);
    }

    public function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        $mileage = (int) $digits;

        return $mileage > 0 ? $mileage : null;
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array{mileage_in: ?int, mileage_out: ?int}
     */
    public function repairOrderMileage(array $legacy): array
    {
        $mileageIn = $this->firstPositiveInt($legacy, [
            'mileage_in',
            'mileageIn',
            'odometer_in',
            'odometer_in_at_checkin',
            'miles_in',
            'vehicle_mileage_in',
            'check_in_mileage',
            'checkin_mileage',
        ]);

        if ($mileageIn === null) {
            $mileageIn = $this->nullablePositiveInt($legacy['mileage'] ?? $legacy['odometer'] ?? null);
        }

        $mileageOut = $this->firstPositiveInt($legacy, [
            'mileage_out',
            'mileageOut',
            'odometer_out',
            'odometer_out_at_checkout',
            'miles_out',
            'vehicle_mileage_out',
            'check_out_mileage',
            'checkout_mileage',
        ]);

        return [
            'mileage_in' => $mileageIn,
            'mileage_out' => $mileageOut,
        ];
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @param  list<string>  $keys
     */
    private function firstPositiveInt(array $legacy, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $legacy)) {
                continue;
            }

            $value = $this->nullablePositiveInt($legacy[$key]);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }
}
