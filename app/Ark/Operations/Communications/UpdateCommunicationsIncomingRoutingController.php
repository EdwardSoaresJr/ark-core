<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Telephony\TelephonyEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateCommunicationsIncomingRoutingController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ring_user_ids' => ['nullable', 'array'],
            'ring_user_ids.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        $enabledUserIds = collect($data['ring_user_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        TelephonyEndpoint::query()
            ->whereNotNull('user_id')
            ->each(function (TelephonyEndpoint $endpoint) use ($enabledUserIds): void {
                $endpoint->enabled = $enabledUserIds->contains($endpoint->user_id);
                $endpoint->save();
            });

        return redirect()
            ->route('operations.shop.communications')
            ->with('status', 'Incoming call routing updated.');
    }
}
