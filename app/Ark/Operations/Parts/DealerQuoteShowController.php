<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DealerQuoteShowController
{
    public function __invoke(Request $request, RepairOrder $repairOrder, DealerQuote $dealerQuote): View
    {
        abort_unless((int) $dealerQuote->repair_order_id === (int) $repairOrder->id, 404);

        $dealerQuote->load(['lines', 'capturedBy']);

        return view('operations.parts.dealer-quote-show', [
            'repairOrder' => $repairOrder,
            'dealerQuote' => $dealerQuote,
        ]);
    }

    public function download(RepairOrder $repairOrder, DealerQuote $dealerQuote): StreamedResponse|Response
    {
        abort_unless((int) $dealerQuote->repair_order_id === (int) $repairOrder->id, 404);
        abort_unless($dealerQuote->hasOriginalDocument(), 404);

        return Storage::disk('local')->download(
            $dealerQuote->storage_path,
            $dealerQuote->original_filename ?: 'dealer-quote.pdf',
        );
    }
}
