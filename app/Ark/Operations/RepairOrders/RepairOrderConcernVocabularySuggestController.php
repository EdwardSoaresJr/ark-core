<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepairOrderConcernVocabularySuggestController
{
    public function __invoke(
        Request $request,
        RepairOrder $repairOrder,
        ScopeEntryVocabularyQuery $vocabulary,
    ): JsonResponse {
        abort_unless($request->user()?->can(\App\Ark\Runtime\Authorization\ArkCapability::RepairOrdersManage->value), 403);

        $payload = $vocabulary->suggest((string) $request->query('q', ''));

        return response()->json($payload);
    }
}
