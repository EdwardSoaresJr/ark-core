<?php

namespace App\Ark\Operations\Telephony;

use App\Ark\Operations\Documents\EstimateSnapshotBuilder;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\Staff\StaffCoachingLogPresenter;
use Illuminate\Support\Carbon;

final class CallCoachingSheetPresenter
{
    public function __construct(
        private readonly CallSessionIntelligenceQuery $query,
        private readonly StaffCoachingLogPresenter $coachingLogs,
        private readonly EstimateSnapshotBuilder $snapshotBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(CallSession $callSession): array
    {
        $callSession->load(['customer', 'owner', 'repairOrder']);

        $row = $this->query->presentRow($callSession);

        return [
            'title' => 'Call Coaching Handout',
            'heading' => 'Advisor coaching review',
            'sheet_type' => 'call-coaching',
            'shop' => $this->snapshotBuilder->presentationLayers()['shop'],
            'printed_at' => Carbon::now()
                ->timezone(ShopDisplayTimezone::resolve())
                ->format('M j, Y g:i A'),
            'row' => $row,
            'coaching_logs' => $this->coachingLogs->forCallSession($callSession),
            'identity' => [
                'document_label' => 'Call coaching',
                'primary_line' => trim(collect([
                    $row['display_phone'],
                    $row['customer_name'],
                ])->filter()->implode(' · ')),
                'secondary_line' => trim(collect([
                    $row['started_at_label'],
                    $row['direction_label'],
                    $row['duration_label'],
                    $row['staff_name'] ? 'Advisor: '.$row['staff_name'] : null,
                    $row['repair_order_number'] ? 'RO #'.$row['repair_order_number'] : null,
                ])->filter()->implode(' · ')),
            ],
        ];
    }
}
