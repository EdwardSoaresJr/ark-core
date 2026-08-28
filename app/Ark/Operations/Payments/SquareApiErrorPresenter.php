<?php

namespace App\Ark\Operations\Payments;

use Square\Exceptions\SquareApiException;

final class SquareApiErrorPresenter
{
    public function messageFor(SquareApiException $exception): string
    {
        foreach ($exception->getErrors() as $error) {
            $detail = trim((string) ($error->getDetail() ?? ''));

            if ($detail !== '') {
                return $this->translateDetail($detail);
            }
        }

        $detail = $this->extractDetailFromMessage((string) $exception->getMessage());

        if ($detail !== null) {
            return $this->translateDetail($detail);
        }

        $message = trim((string) $exception->getMessage());

        return $message !== '' ? $message : 'Square payment request failed.';
    }

    private function extractDetailFromMessage(string $message): ?string
    {
        if (preg_match('/Body:\s*(\{.*\})\s*$/s', $message, $matches) !== 1) {
            return null;
        }

        $body = json_decode($matches[1], true);

        if (! is_array($body)) {
            return null;
        }

        $errors = $body['errors'] ?? [];

        if (! is_array($errors) || $errors === []) {
            return null;
        }

        $detail = trim((string) ($errors[0]['detail'] ?? ''));

        return $detail !== '' ? $detail : null;
    }

    private function translateDetail(string $detail): string
    {
        if (preg_match('/Merchant not authorized for device_id=/i', $detail) === 1) {
            return 'Square terminal is not authorized for this merchant account. In Square Dashboard → Devices, confirm the reader is paired to the same account and location as Settings → Payments, then update the Terminal device ID if needed. Keyed card entry still works when enabled.';
        }

        if (preg_match('/not found.*device/i', $detail) === 1) {
            return 'Square terminal device ID was not found. Copy the device ID from Square Dashboard → Devices into Settings → Payments → Terminal device ID.';
        }

        return $detail;
    }
}
