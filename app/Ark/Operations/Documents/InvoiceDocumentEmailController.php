<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceDocumentEmailController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        InvoiceDocumentEmailDelivery $delivery,
        RepairOrderConcurrency $concurrency,
    ): RedirectResponse {
        $concurrency->guard($request, $repairOrder);

        $data = $request->validate([
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $recipientEmail = strtolower(trim($data['email'] ?? $repairOrder->customer->email ?? ''));

        if ($recipientEmail === '') {
            throw ValidationException::withMessages([
                'email' => 'Add a customer email on file or enter one to send the invoice.',
            ])->redirectTo($this->redirectBack($request, $repairOrder));
        }

        try {
            $delivery->send(
                $repairOrder,
                $request->user(),
                $recipientEmail,
                $data['message'] ?? null,
            );
        } catch (EstimatePdfUnavailableException) {
            return redirect()
                ->to($this->redirectBack($request, $repairOrder))
                ->with('status', 'Invoice email failed. The PDF could not be generated — check Chromium runtime support.');
        }

        return redirect()
            ->to($this->redirectBack($request, $repairOrder))
            ->with('status', 'Invoice emailed to '.$recipientEmail.'.');
    }

    private function redirectBack(Request $request, RepairOrder $repairOrder): string
    {
        $intended = $request->headers->get('referer');

        if (is_string($intended) && $intended !== '') {
            return $intended;
        }

        return route('operations.repair-orders.show', $repairOrder);
    }
}
