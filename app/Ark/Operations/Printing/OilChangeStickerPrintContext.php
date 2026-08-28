<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use App\Ark\Operations\Maintenance\OilChangeStickerGate;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopSettings;
use Carbon\Carbon;
use Illuminate\Support\Str;

final class OilChangeStickerPrintContext
{
    public const VEHICLE_LINE_LIMIT = 30;

    public const VEHICLE_WITH_MILEAGE_COMBINED_MAX_CHARS = 44;

    public function __construct(
        public readonly string $stickerTitleLine,
        public readonly string $shopLine,
        public readonly string $vehicleLine,
        public readonly ?string $mileageCurrentLine,
        public readonly ?string $vehicleLineWithCurrentMileage,
        public readonly ?string $nextOilDueLine,
        public readonly ?string $oilTypeLine,
        public readonly string $printedDateLine,
        public readonly ?string $dueMileageAndDateLine,
        public readonly ?string $qrCodeSvg = null,
    ) {}

    public static function fromRepairOrder(RepairOrder $repairOrder, ?string $qrCodeSvg = null, string $stickerTitleLine = ''): self
    {
        $repairOrder->loadMissing(['vehicle']);

        $shopLine = Str::limit(trim((string) (ShopSettings::current()->shop_name ?? '')), 32);

        $vehicle = $repairOrder->vehicle;
        $vehRaw = trim(implode(' ', array_filter([
            $vehicle?->year,
            $vehicle?->make,
            $vehicle?->model,
        ])));
        $vehicleLine = Str::limit($vehRaw !== '' ? $vehRaw : 'Vehicle', self::VEHICLE_LINE_LIMIT);

        $event = OilChangeStickerGate::currentEventForRepairOrder($repairOrder);

        $miOut = $repairOrder->resolvedMileageOut();
        $miIn = $repairOrder->resolvedMileageIn();
        $mi = null;
        if ($event?->service_mileage !== null && $event->service_mileage > 0) {
            $mi = (int) $event->service_mileage;
        } elseif ($miOut !== null && $miOut > 0) {
            $mi = $miOut;
        } elseif ($miIn !== null && $miIn > 0) {
            $mi = $miIn;
        }

        $mileageCurrentLine = $mi !== null && $mi > 0
            ? __(':n mi', ['n' => number_format($mi)])
            : null;

        $vehicleLineWithCurrentMileage = null;
        if ($mileageCurrentLine !== null && $mileageCurrentLine !== '') {
            $vehBase = $vehRaw !== '' ? $vehRaw : 'Vehicle';
            $vehicleLineWithCurrentMileage = self::formatVehicleWithCurrentMileage($vehBase, $mileageCurrentLine);
        }

        $interval = ShopPrintingSettings::oilChangeIntervalMiles();
        $nextOilDueLine = null;
        $nextFromEvent = $event?->next_due_mileage;
        if ($nextFromEvent !== null && $nextFromEvent > 0) {
            $nextOilDueLine = __('Next: :n mi', ['n' => number_format($nextFromEvent)]);
        } elseif ($mi !== null && $mi > 0 && $interval > 0) {
            $next = $mi + $interval;
            $nextOilDueLine = __('Next: :n mi', ['n' => number_format($next)]);
        }

        $dueMonths = ShopPrintingSettings::oilChangeNextDueMonths();
        $anchor = ($event?->confirmed_at ?? $repairOrder->closed_at ?? $repairOrder->opened_at ?? $repairOrder->created_at)
            ?->copy()
            ->startOfDay() ?? Carbon::now()->startOfDay();

        $nextDueDate = $anchor->copy()->addMonths($dueMonths);
        $todayStart = Carbon::now()->startOfDay();
        if ($nextDueDate->lt($todayStart)) {
            $nextDueDate = $todayStart->copy()->addMonths($dueMonths);
        }

        $dateOnly = $nextDueDate->format('n/j/Y');
        $printedDateLine = __('Due: :date', ['date' => $dateOnly]);

        $miSeg = null;
        if ($nextFromEvent !== null && $nextFromEvent > 0) {
            $miSeg = __(':n mi', ['n' => number_format($nextFromEvent)]);
        } elseif ($mi !== null && $mi > 0 && $interval > 0) {
            $next = $mi + $interval;
            $miSeg = __(':n mi', ['n' => number_format($next)]);
        }

        $dueMileageAndDateLine = $miSeg !== null
            ? __('Due: :mi or :date', ['mi' => $miSeg, 'date' => $dateOnly])
            : __('Due: :date', ['date' => $dateOnly]);

        $title = trim($stickerTitleLine) !== '' ? Str::limit(trim($stickerTitleLine), 24) : '';
        $oilTypeLine = null;
        if ($event !== null) {
            $oilTypeLine = trim(implode(' ', array_filter([
                $event->viscosity,
                $event->oil_brand,
            ])));
            $oilTypeLine = $oilTypeLine !== '' ? Str::limit($oilTypeLine, 64) : null;
        }
        if ($oilTypeLine === null) {
            $prepared = OilChangeStickerGate::activeServiceForRepairOrder($repairOrder);
            if ($prepared !== null) {
                $oilTypeLine = trim(implode(' ', array_filter([
                    $prepared->prepared_viscosity,
                    $prepared->prepared_oil_brand,
                ])));
                $oilTypeLine = $oilTypeLine !== '' ? Str::limit($oilTypeLine, 64) : null;
            }
        }
        $oilTypeLine ??= OilChangeStickerOilTypeResolver::inferLine($repairOrder);

        return new self(
            stickerTitleLine: $title,
            shopLine: $shopLine,
            vehicleLine: $vehicleLine,
            mileageCurrentLine: $mileageCurrentLine,
            vehicleLineWithCurrentMileage: $vehicleLineWithCurrentMileage,
            nextOilDueLine: $nextOilDueLine,
            oilTypeLine: $oilTypeLine,
            printedDateLine: $printedDateLine,
            dueMileageAndDateLine: $dueMileageAndDateLine,
            qrCodeSvg: $qrCodeSvg,
        );
    }

    private static function formatVehicleWithCurrentMileage(string $vehicleRaw, string $mileageLine): string
    {
        $sep = ' · ';
        $suffix = $sep.$mileageLine;
        $maxVehicleChars = self::VEHICLE_WITH_MILEAGE_COMBINED_MAX_CHARS - mb_strlen($suffix);
        if ($maxVehicleChars < 8) {
            $maxVehicleChars = 8;
        }

        $veh = Str::limit(trim($vehicleRaw) !== '' ? trim($vehicleRaw) : '—', $maxVehicleChars, '');

        return $veh.$suffix;
    }
}
