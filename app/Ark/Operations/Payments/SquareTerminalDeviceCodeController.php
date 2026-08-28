<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Payments\Contracts\SquarePaymentsClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class SquareTerminalDeviceCodeController
{
    public function __construct(
        private readonly CreateSquareTerminalDeviceCodeAction $createDeviceCode,
        private readonly ApplySquareTerminalPairingAction $applyPairing,
        private readonly SquarePaymentsClient $square,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $result = $this->createDeviceCode->execute($data['name'] ?? null);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('operations.settings.shop.edit', ['section' => 'payments'])
                ->withErrors($exception->errors());
        } catch (SquarePaymentRequestException $exception) {
            return redirect()
                ->route('operations.settings.shop.edit', ['section' => 'payments'])
                ->withErrors(['square_terminal_pairing' => $exception->getMessage()]);
        }

        return redirect()
            ->route('operations.settings.shop.edit', ['section' => 'payments'])
            ->with('status', 'Square terminal pairing code generated. Enter it on the reader within 5 minutes.')
            ->with('square_terminal_pairing', [
                'id' => $result->id,
                'code' => $result->code,
                'pair_by' => $result->pairBy,
                'status' => $result->status,
            ]);
    }

    public function show(string $deviceCodeId): JsonResponse
    {
        try {
            $result = $this->square->getTerminalDeviceCode($deviceCodeId);
        } catch (SquarePaymentRequestException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        if ($result->paired()) {
            $this->applyPairing->execute((string) $result->deviceId);
        }

        return response()->json([
            'id' => $result->id,
            'code' => $result->code,
            'status' => $result->status,
            'pair_by' => $result->pairBy,
            'device_id' => $result->deviceId,
            'paired' => $result->paired(),
        ]);
    }
}
