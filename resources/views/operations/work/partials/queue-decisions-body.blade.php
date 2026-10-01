<div @class([
    'ops-work-planned-grid' => ! ($full ?? false),
    'ops-radar ops-radar--decisions ops-radar--decisions-full' => ($full ?? false),
])>
    @include('operations.attention.partials.customer-decision-pressure-section', [
        'variant' => 'lane',
        'tone' => 'approval',
        'title' => 'Customer Decision Needed',
        'note' => 'Estimate exists · no approval · not paid.',
        'rows' => $customer_decision_pressure['customer_decision_needed'] ?? [],
        'empty' => 'No open estimates waiting on a customer decision.',
        'compactActions' => $compactActions ?? false,
        'compactHeader' => ! ($full ?? false),
    ])

    @include('operations.attention.partials.customer-decision-pressure-section', [
        'variant' => 'lane',
        'tone' => 'blocked',
        'title' => 'Approved Work Stalled',
        'note' => 'Customer approved work · payment not collected.',
        'rows' => $customer_decision_pressure['approved_work_stalled'] ?? [],
        'empty' => 'No approved work stalled without payment.',
        'compactActions' => $compactActions ?? false,
        'compactHeader' => ! ($full ?? false),
    ])

    @include('operations.attention.partials.customer-decision-pressure-section', [
        'variant' => 'lane',
        'tone' => 'move',
        'title' => 'Estimate Ready · Not Sent',
        'note' => 'Estimate built · no customer-facing send recorded.',
        'rows' => $customer_decision_pressure['estimate_ready_not_sent'] ?? [],
        'empty' => 'No unsent estimates waiting on delivery.',
        'compactActions' => $compactActions ?? false,
        'compactHeader' => ! ($full ?? false),
    ])
</div>
