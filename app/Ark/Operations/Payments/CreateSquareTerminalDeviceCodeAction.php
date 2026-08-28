<?php

namespace App\Ark\Operations\Payments;

use App\Ark\Operations\Payments\Contracts\SquarePaymentsClient;
use Illuminate\Validation\ValidationException;

final class CreateSquareTerminalDeviceCodeAction
{
    public function __construct(
        private readonly SquareConfiguration $configuration,
        private readonly SquarePaymentsClient $square,
    ) {}

    public function execute(?string $name = null): SquareTerminalDeviceCodeResult
    {
        if (! $this->configuration->configured()) {
            throw ValidationException::withMessages([
                'square' => 'Save Square credentials before generating a terminal pairing code.',
            ]);
        }

        if ($this->configuration->isSandbox()) {
            throw ValidationException::withMessages([
                'square' => 'Terminal pairing codes require production Square credentials. Sandbox uses documented test device IDs instead.',
            ]);
        }

        $locationId = $this->configuration->locationId();

        if ($locationId === '') {
            throw ValidationException::withMessages([
                'square_location_id' => 'Location ID is required before generating a terminal pairing code.',
            ]);
        }

        return $this->square->createTerminalDeviceCode($name);
    }
}
