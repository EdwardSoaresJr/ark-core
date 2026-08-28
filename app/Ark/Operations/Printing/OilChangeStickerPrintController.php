<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final class OilChangeStickerPrintController
{
    public function __construct(
        private readonly OilChangeStickerPdfRenderer $renderer,
        private readonly ResponseFactory $response,
    ) {}

    public function __invoke(Request $request, RepairOrder $repairOrder): Response
    {
        $this->logPrintJob($request, $repairOrder);

        $bytes = $this->renderer->renderPdfBytesForRepairOrder($repairOrder);
        $label = 'Oil-Sticker-'.$repairOrder->repair_order_id;

        return $this->response->make($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$label.'.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function logPrintJob(Request $request, RepairOrder $repairOrder): void
    {
        $jobId = trim((string) $request->header('X-Print-Job-Id', ''));
        if ($jobId === '') {
            return;
        }

        Log::info('qz.print_pdf_request', [
            'context' => 'oil_change_sticker',
            'repair_order_id' => $repairOrder->repair_order_id,
            'print_job_id' => $jobId,
            'print_batch_id' => $request->header('X-Print-Batch-Id'),
            'print_printer' => $request->header('X-Print-Printer'),
            'print_source' => $request->header('X-Print-Source'),
        ]);
    }
}
