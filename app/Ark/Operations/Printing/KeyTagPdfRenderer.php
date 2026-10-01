<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use App\Ark\Operations\RepairOrders\RepairOrder;

final class KeyTagPdfRenderer
{
    /** @var list<string> */
    private const DEFAULT_BLOCKS = [
        'business_name',
        'customer_name',
        'vehicle_info',
        'license_plate',
        'vin_last8',
    ];

    public function __construct(
        private readonly LabelPdfRenderer $labelPdf,
    ) {}

    public function renderPdfBytesForRepairOrder(RepairOrder $repairOrder): string
    {
        $ctx = KeyTagPrintContext::fromRepairOrder(
            $repairOrder,
            ShopPrintingSettings::keyTagVinDisplayMode(),
        );

        $w = ShopPrintingSettings::keyTagLabelWidthMm();
        $h = ShopPrintingSettings::keyTagLabelHeightMm();

        return $this->labelPdf->renderBytes('operations.printing.key-tag', [
            'keyTagPrintContext' => $ctx,
            'keyTagBlocks' => self::DEFAULT_BLOCKS,
            'labelWidthMm' => $w,
            'labelHeightMm' => $h,
        ], $w, $h);
    }
}
