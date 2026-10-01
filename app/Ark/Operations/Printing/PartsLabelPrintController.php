<?php

declare(strict_types=1);

namespace App\Ark\Operations\Printing;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PartsLabelPrintController
{
    public function __construct(
        private readonly PartsLabelPdfRenderer $renderer,
        private readonly ResponseFactory $response,
    ) {}

    public function __invoke(Request $request, RepairOrder $repairOrder, RepairOrderLine $line): Response
    {
        if ((int) $line->repair_order_id !== (int) $repairOrder->id) {
            throw new NotFoundHttpException;
        }

        if (! $line->isPart()) {
            abort(422, 'Parts labels are only available for part lines.');
        }

        $of = max(1, (int) $request->query('of', PartsLabelPrintContext::stickerCountForQuantity($line->quantity)));
        $copy = max(1, (int) $request->query('copy', 1));
        if ($copy > $of) {
            abort(422, 'Parts label copy index is out of range.');
        }

        $this->logPrintJob($request, $repairOrder, $line, $copy, $of);

        $bytes = $this->renderer->renderPdfBytesForLine($line, $copy, $of);
        $label = 'Parts-Label-'.$repairOrder->repair_order_id.'-'.$line->id.'-'.$copy.'-of-'.$of;

        return $this->response->make($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$label.'.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function logPrintJob(
        Request $request,
        RepairOrder $repairOrder,
        RepairOrderLine $line,
        int $copy,
        int $of,
    ): void {
        $jobId = trim((string) $request->header('X-Print-Job-Id', ''));
        if ($jobId === '') {
            return;
        }

        Log::info('qz.print_pdf_request', [
            'context' => 'parts_label',
            'repair_order_id' => $repairOrder->repair_order_id,
            'line_id' => $line->id,
            'copy' => $copy,
            'of' => $of,
            'print_job_id' => $jobId,
            'print_batch_id' => $request->header('X-Print-Batch-Id'),
            'print_printer' => $request->header('X-Print-Printer'),
            'print_source' => $request->header('X-Print-Source'),
        ]);
    }
}
