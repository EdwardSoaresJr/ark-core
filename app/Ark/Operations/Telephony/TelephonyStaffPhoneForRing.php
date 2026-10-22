<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\PhoneNumber;
use App\Models\User;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;

class TelephonyStaffPhoneForRing
{
    /**
     * @param  array<int, array<string, mixed>>  $endpoints
     * @return array<int, array<string, mixed>>
     */
    public function applyAndValidate(array $endpoints): array
    {
        $errors = new MessageBag;

        foreach ($endpoints as $index => $endpoint) {
            $type = TelephonyEndpointType::tryFrom((string) ($endpoint['type'] ?? ''));

            if ($type !== TelephonyEndpointType::Cell) {
                continue;
            }

            if (! filter_var($endpoint['enabled'] ?? true, FILTER_VALIDATE_BOOL)) {
                continue;
            }

            $userId = filled($endpoint['user_id'] ?? null) ? (int) $endpoint['user_id'] : null;

            if ($userId === null) {
                $errors->add("endpoints.{$index}.user_id", 'Select a staff member for each enabled cell endpoint.');

                continue;
            }

            $user = User::query()->find($userId);

            if ($user === null) {
                continue;
            }

            $entered = trim((string) ($endpoint['destination'] ?? ''));

            if ($entered !== '') {
                if (strlen((string) PhoneNumber::normalize($entered)) !== 10) {
                    $errors->add("endpoints.{$index}.destination", 'Enter a 10-digit cell number for this endpoint.');
                }

                continue;
            }

            if (PhoneNumber::normalize($user->phone) === null) {
                $errors->add(
                    "endpoints.{$index}.user_id",
                    "Enter a cell number for this endpoint. {$user->name} has no cell on their profile.",
                );
            }
        }

        if ($errors->isNotEmpty()) {
            throw ValidationException::withMessages($errors->getMessages());
        }

        return $endpoints;
    }
}
