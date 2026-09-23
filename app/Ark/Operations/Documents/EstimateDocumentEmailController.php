<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\RepairOrders\RecordEstimateSentWithMissingVinAction;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EstimateDocumentEmailController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        EstimateDocumentEmailDelivery $delivery,
        RepairOrderConcurrency $concurrency,
        RecordEstimateSentWithMissingVinAction $recordMissingVinOverride,
    ): RedirectResponse {
        $concurrency->guard($request, $repairOrder);

        if ($repairOrder->lines()->doesntExist()) {
            throw ValidationException::withMessages([
                'email' => 'Add at least one estimate line before emailing the customer.',
            ])->redirectTo($this->redirectBack($request, $repairOrder));
        }

        try {
            $repairOrder->ensureEstimateSendAllowed(
                $request->boolean('acknowledge_missing_vin'),
            );
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'email' => $exception->getMessage(),
            ])->redirectTo($this->redirectBack($request, $repairOrder));
        }

        $data = $request->validate([
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:500'],
            'acknowledge_missing_vin' => ['nullable', 'boolean'],
        ]);

        $recipientEmail = strtolower(trim($data['email'] ?? $repairOrder->customer->email ?? ''));

        if ($recipientEmail === '') {
            throw ValidationException::withMessages([
                'email' => 'Add a customer email on file or enter one to send the estimate.',
            ])->redirectTo($this->redirectBack($request, $repairOrder));
        }

        try {
            $emailResult = $delivery->send(
                $repairOrder,
                $request->user(),
                $recipientEmail,
                $data['message'] ?? null,
            );
        } catch (EstimatePdfUnavailableException) {
            return redirect()
                ->to($this->redirectBack($request, $repairOrder))
                ->with('status', 'Estimate email failed. The PDF could not be generated — check Chromium runtime support.');
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'email' => $exception->getMessage(),
            ])->redirectTo($this->redirectBack($request, $repairOrder));
        }

        if ($request->boolean('acknowledge_missing_vin')) {
            $recordMissingVinOverride->record($repairOrder, $request->user(), 'email');
        }

        $status = 'Estimate emailed to '.$recipientEmail.'.';
        $toast = $emailResult['awaiting_approval']['toast'] ?? null;

        if (is_string($toast) && $toast !== '') {
            $status .= ' '.$toast;
        }

        return redirect()
            ->to($this->redirectBack($request, $repairOrder))
            ->with('status', $status);
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
