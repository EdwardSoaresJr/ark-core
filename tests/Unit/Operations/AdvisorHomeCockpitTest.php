<?php

use App\Ark\Operations\Flow\FlowStageKey;
use App\Ark\Operations\Today\AdvisorHomeCockpitProjection;

test('home column mapping links flow constraint to advisor board column', function () {
    expect(AdvisorHomeCockpitProjection::homeColumnKeyForStage(FlowStageKey::WaitingApproval))
        ->toBe('waiting_approval')
        ->and(AdvisorHomeCockpitProjection::homeColumnKeyForStage(FlowStageKey::WaitingParts))
        ->toBe('parts')
        ->and(AdvisorHomeCockpitProjection::homeColumnKeyForStage(FlowStageKey::ReadyPickup))
        ->toBe('completed');
});
