<?php

namespace App\Ark\Operations\Today;

use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TodayRecommendationSnoozeController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'repair_order_id' => ['required', 'integer', 'min:1'],
            'duration' => ['required', Rule::enum(TodayRecommendationSnoozeDuration::class)],
        ]);

        $repairOrder = RepairOrder::query()
            ->where('repair_order_id', (int) $data['repair_order_id'])
            ->firstOrFail();

        $duration = TodayRecommendationSnoozeDuration::from($data['duration']);
        $snoozedAt = now();
        $snoozedUntil = $duration->snoozedUntil($snoozedAt);

        TodayRecommendationSnooze::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'repair_order_id' => $repairOrder->repair_order_id,
            ],
            [
                'snoozed_at' => $snoozedAt,
                'snoozed_until' => $snoozedUntil,
            ],
        );

        return redirect()
            ->route('operations.index')
            ->with(
                'status',
                'RO #'.$repairOrder->repair_order_id.' snoozed until '.$snoozedUntil->format('M j, g:i A').'.',
            );
    }
}
