<?php

use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Portal\PortalCustomerAuthorizationPresentation;

test('portal customer authorization presentation uses customer-facing method labels', function (): void {
    expect(PortalCustomerAuthorizationPresentation::customerSourceLabel(ApprovalSource::Portal))
        ->toBe('Online estimate')
        ->and(PortalCustomerAuthorizationPresentation::customerSourceLabel(ApprovalSource::Sms))
        ->toBe('Text message')
        ->and(PortalCustomerAuthorizationPresentation::customerSourceLabel(ApprovalSource::InPerson))
        ->toBe('In person at the shop');
});

test('portal customer authorization presentation formats session flash records', function (): void {
    $record = PortalCustomerAuthorizationPresentation::fromSessionFlash([
        'approved_by' => 'Morgan Brown',
        'approved_at_label' => 'Jun 10, 2026 9:02 AM',
        'source' => ApprovalSource::Portal->value,
        'approved_amount' => '$507.26',
    ]);

    expect($record['approved_by'])->toBe('Morgan Brown')
        ->and($record['approved_at_label'])->toBe('Jun 10, 2026 9:02 AM')
        ->and($record['source_label'])->toBe('Online estimate')
        ->and($record['approved_amount'])->toBe('$507.26');
});
