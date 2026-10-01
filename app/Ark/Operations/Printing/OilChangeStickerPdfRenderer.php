<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use App\Ark\Operations\RepairOrders\RepairOrder;

final class OilChangeStickerPdfRenderer
{
    /** @var list<string> */
    private const DEFAULT_BLOCKS = [
        'shop_line',
        'oil_vehicle_info',
        'oil_type',
        'printed_date',
    ];

    public function __construct(
        private readonly LabelPdfRenderer $labelPdf,
    ) {}

    public function renderPdfBytesForRepairOrder(RepairOrder $repairOrder): string
    {
        $ctx = OilChangeStickerPrintContext::fromRepairOrder($repairOrder);

        $w = ShopPrintingSettings::oilStickerLabelWidthMm();
        $h = ShopPrintingSettings::oilStickerLabelHeightMm();

        return $this->labelPdf->renderBytes('operations.printing.oil-change-sticker', [
            'oilStickerPrintContext' => $ctx,
            'oilStickerBlocks' => self::DEFAULT_BLOCKS,
            'oilStickerDueCombinedSlot' => 'printed',
            'oilStickerMergeVehicleMileage' => true,
            'labelWidthMm' => $w,
            'labelHeightMm' => $h,
        ], $w, $h);
    }
}
