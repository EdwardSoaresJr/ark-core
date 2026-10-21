<?php

namespace App\Ark\Operations\Parts;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Repair-order actions for a linked external quote. Import stays on its own route.
 */
class RepairOrderLinkedPartsQuoteController
{
    public function show(RepairOrder $repairOrder, LinkedPartsQuotes $quotes): JsonResponse
    {
        return response()->json($quotes->present($repairOrder));
    }

    public function refresh(Request $request, RepairOrder $repairOrder, LinkedPartsQuotes $quotes): JsonResponse
    {
        abort_unless($quotes->available(), 404);

        return $this->result($quotes->refresh($repairOrder, $this->user($request)));
    }

    public function candidates(Request $request, RepairOrder $repairOrder, LinkedPartsQuotes $quotes): JsonResponse
    {
        abort_unless($quotes->available(), 404);

        return $this->result($quotes->candidates($repairOrder, $this->user($request)));
    }

    public function store(Request $request, RepairOrder $repairOrder, LinkedPartsQuotes $quotes): JsonResponse
    {
        abort_unless($quotes->available(), 404);
        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:64'],
        ]);

        return $this->result($quotes->link($repairOrder, $this->user($request), $data['external_id']));
    }

    public function current(Request $request, RepairOrder $repairOrder, LinkedPartsQuotes $quotes): JsonResponse
    {
        abort_unless($quotes->available(), 404);
        $data = $request->validate([
            'link_id' => ['required', 'integer'],
        ]);

        return $this->result($quotes->makeCurrent($repairOrder, $this->user($request), (string) $data['link_id']));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function result(array $result): JsonResponse
    {
        $status = ($result['ok'] ?? false) === true ? 200 : 422;

        return response()->json($result, $status);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
