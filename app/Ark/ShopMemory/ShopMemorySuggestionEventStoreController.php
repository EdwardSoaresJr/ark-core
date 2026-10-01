<?php

namespace App\Ark\ShopMemory;

use App\Ark\ShopMemory\Suggestion\SuggestionOutcome;
use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Record one terminal outcome per suggestion interaction.
 * Does not influence ranking.
 */
final class ShopMemorySuggestionEventStoreController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can(ArkCapability::RepairOrdersManage->value), 403);

        $data = $request->validate([
            'provider_key' => ['required', 'string', 'max:64'],
            'suggestion_id' => ['nullable', 'string', 'max:128'],
            'outcome' => ['required', 'string', 'in:accepted_unchanged,accepted_edited,ignored,dismissed'],
            'surface' => ['required', 'string', 'max:64'],
            'query' => ['nullable', 'string', 'max:255'],
            'repair_order_id' => ['nullable', 'integer'],
        ]);

        $event = ShopMemorySuggestionEvent::query()->create([
            'provider_key' => $data['provider_key'],
            'suggestion_id' => $data['suggestion_id'] ?? null,
            'outcome' => SuggestionOutcome::from($data['outcome']),
            'surface' => $data['surface'],
            'query' => $data['query'] ?? null,
            'repair_order_id' => $data['repair_order_id'] ?? null,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json(['id' => $event->id], 201);
    }
}
