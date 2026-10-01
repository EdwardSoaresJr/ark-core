<?php

namespace App\Ark\Operations\RepairOrders;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class RepairOrderWorkGroupLineValidator
{
    public static function attachRules(Request $request, RepairOrder $repairOrder): array
    {
        return [
            'repair_order_work_group_id' => [
                'nullable',
                'integer',
                Rule::exists('repair_order_work_groups', 'id')->where(
                    fn ($query) => $query->whereIn(
                        'repair_order_concern_id',
                        $repairOrder->concerns()->select('id'),
                    ),
                ),
            ],
        ];
    }

    public static function validateConcernAlignment(Validator $validator, Request $request): void
    {
        $workGroupId = $request->input('repair_order_work_group_id');
        $concernId = $request->input('repair_order_concern_id');

        if (! $workGroupId || ! $concernId) {
            return;
        }

        $workGroupConcernId = RepairOrderWorkGroup::query()
            ->whereKey($workGroupId)
            ->value('repair_order_concern_id');

        if ((int) $workGroupConcernId !== (int) $concernId) {
            $validator->errors()->add(
                'repair_order_work_group_id',
                'Repair must belong to the same scope as this line.',
            );
        }
    }
}
