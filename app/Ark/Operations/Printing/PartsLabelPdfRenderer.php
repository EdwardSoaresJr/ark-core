<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use App\Ark\Operations\RepairOrders\RepairOrderLine;

final class PartsLabelPdfRenderer
{
    public function __construct(
        private readonly LabelPdfRenderer $labelPdf,
    ) {}

    public function renderPdfBytesForLine(RepairOrderLine $line, int $copy = 1, int $of = 1): string
    {
        $ctx = PartsLabelPrintContext::fromLine($line, $copy, $of);

        $w = ShopPrintingSettings::keyTagLabelWidthMm();
        $h = ShopPrintingSettings::keyTagLabelHeightMm();

        return $this->labelPdf->renderBytes('operations.printing.parts-label', [
            'partsLabelPrintContext' => $ctx,
            'labelWidthMm' => $w,
            'labelHeightMm' => $h,
        ], $w, $h);
    }
}
