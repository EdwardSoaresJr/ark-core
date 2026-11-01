<div class="ark-board pb-2" data-work-decisions>
    @foreach ([
        ['tone' => 'approval', 'title' => 'Customer Decision Needed', 'note' => 'Estimate exists · no approval · not paid.', 'key' => 'customer_decision_needed', 'empty' => 'No open estimates waiting on a customer decision.'],
        ['tone' => 'blocked', 'title' => 'Approved Work Stalled', 'note' => 'Customer approved work · payment not collected.', 'key' => 'approved_work_stalled', 'empty' => 'No approved work stalled without payment.'],
        ['tone' => 'move', 'title' => 'Estimate Ready · Not Sent', 'note' => 'Estimate built · no customer-facing send recorded.', 'key' => 'estimate_ready_not_sent', 'empty' => 'No unsent estimates waiting on delivery.'],
    ] as $lane)
        <div>
            @include('operations.attention.partials.customer-decision-pressure-section', [
                'variant' => 'lane',
                'tone' => $lane['tone'],
                'title' => $lane['title'],
                'note' => $lane['note'],
                'rows' => $customer_decision_pressure[$lane['key']] ?? [],
                'empty' => $lane['empty'],
                'compactActions' => $compactActions ?? false,
                'compactHeader' => ! ($full ?? false),
            ])
        </div>
    @endforeach
</div>
