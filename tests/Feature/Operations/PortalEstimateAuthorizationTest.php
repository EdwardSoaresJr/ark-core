<?php

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Approvals\ApprovalType;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\CustomerFacingEstimateStatus;
use App\Ark\Operations\Documents\DocumentFooterPresenter;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\EstimateSnapshotBuilder;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\Financial\LedgerEntryType;
use App\Ark\Operations\Financial\PaymentMethod;
use App\Ark\Operations\Financial\RecordLedgerEntryAction;
use App\Ark\Operations\Financial\RepairOrderLedgerEntry;
use App\Ark\Operations\Payments\PaymentCaptureSurface;
use App\Ark\Operations\Payments\PaymentGatewayAttemptStatus;
use App\Ark\Operations\Portal\EstimateAccessToken;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderPosture;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Mail\EstimateCustomerMail;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['portal_signature_required' => false]);
});

test('a valid estimate token renders recommended work and hides draft work', function () {
    [$repairOrder, , $recommendedConcern] = portalAuthorizationRepairOrder();

    $draft = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Draft internal diagnostic',
        'disposition' => RepairOrderConcernDisposition::Draft,
        'position' => 2,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $draft->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Internal draft labor',
        'quantity' => '1.00',
        'unit_price_cents' => 9900,
    ]);

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk()
        ->assertSee($recommendedConcern->summary, false)
        ->assertSee('A/C performance diagnostic', false)
        ->assertDontSee('Draft internal diagnostic', false)
        ->assertDontSee('Internal draft labor', false)
        ->assertDontSee('public-financing-note', false)
        ->assertSee('Choose what to approve', false);

    $this->get(route('portal.estimates.show', ['token' => str_repeat('d', 64)]))
        ->assertNotFound();
});

test('portal authorization submit button reflects default all-approved selection', function () {
    portalAuthorizationRepairOrder();

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk()
        ->assertSee('Choose what to approve', false)
        ->assertSee('Approve all services', false)
        ->assertDontSee('Submit authorization');
});

test('portal customer can approve recommended concerns', function () {
    [$repairOrder, $token, $recommendedConcern] = portalAuthorizationRepairOrder();

    $this->post(route('portal.estimates.authorize', ['token' => portalAuthorizationPlainToken()]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [
            $recommendedConcern->id => RepairOrderConcernDisposition::Approved->value,
        ],
    ])->assertRedirect(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertSessionHas('portal_authorization');

    $recommendedConcern->refresh();

    expect($recommendedConcern->disposition)->toBe(RepairOrderConcernDisposition::Approved);

    $approval = ApprovalEvent::query()->sole();

    expect($approval)
        ->source->toBe(ApprovalSource::Portal)
        ->approval_type->toBe(ApprovalType::Repair)
        ->approved_by->toBe('Morgan Brown')
        ->and($approval->approved_amount_cents)->toBeGreaterThan(0);

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk()
        ->assertSee('You’re all set')
        ->assertSee('What happens next')
        ->assertSee('All set')
        ->assertDontSee('Submit authorization');
});

test('portal shows read-only approval notice when presented work is already approved', function () {
    [$repairOrder, $token, $recommendedConcern] = portalAuthorizationRepairOrder();

    $recommendedConcern->update(['disposition' => RepairOrderConcernDisposition::Approved]);

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk()
        ->assertSee('Work approved')
        ->assertSee('We’ve recorded the work you authorized with your advisor.')
        ->assertSee('Approved', false)
        ->assertDontSee('Confirm authorization')
        ->assertDontSee('Authorize estimate')
        ->assertDontSee('Submit authorization');

    $this->post(route('portal.estimates.authorize', ['token' => portalAuthorizationPlainToken()]), [
        'confirmed_name' => 'Morgan Brown',
    ])->assertRedirect()
        ->assertSessionHasErrors('authorization');

    expect(ApprovalEvent::query()->count())->toBe(0);
});

test('portal shows authorization source when staff recorded approval in shop', function () {
    [$repairOrder, $token, $recommendedConcern] = portalAuthorizationRepairOrder();

    $recommendedConcern->update(['disposition' => RepairOrderConcernDisposition::Approved]);

    ApprovalEvent::query()->create([
        'visit_id' => $repairOrder->id,
        'approval_type' => ApprovalType::Repair,
        'approved_amount_cents' => 15593,
        'source' => ApprovalSource::Phone,
        'approved_by' => 'Morgan Brown',
        'approved_at' => now(),
    ]);

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk()
        ->assertSee('We have your approval')
        ->assertSee('We’ve recorded the work you authorized with your advisor.')
        ->assertSee('Approved by')
        ->assertSee('Morgan Brown')
        ->assertSee('Phone')
        ->assertDontSee('Submit authorization')
        ->assertDontSee('Work approved');
});

test('portal customer can defer recommended concerns', function () {
    [$repairOrder, $token, $recommendedConcern] = portalAuthorizationRepairOrder();

    $this->post(route('portal.estimates.authorize', ['token' => portalAuthorizationPlainToken()]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [
            $recommendedConcern->id => RepairOrderConcernDisposition::Deferred->value,
        ],
    ])->assertRedirect()
        ->assertSessionHas('portal_authorization');

    $recommendedConcern->refresh();

    expect($recommendedConcern->disposition)->toBe(RepairOrderConcernDisposition::Deferred);

    $approval = ApprovalEvent::query()->sole();

    expect($approval)
        ->source->toBe(ApprovalSource::Portal)
        ->approval_type->toBe(ApprovalType::Partial)
        ->and($approval->approved_amount_cents)->toBe(0);

    expect(app(CustomerFacingEstimateStatus::class)
        ->labelForRepairOrder($repairOrder->fresh(['concerns'])))
        ->toBe('Deferred for follow-up');

    $event = CommunicationEvent::query()->sole();

    expect($event->summary)->toBe('Customer responded via portal (0 approved, 1 deferred, 0 declined).');
});

test('portal customer can decline recommended concerns', function () {
    [$repairOrder, $token, $recommendedConcern] = portalAuthorizationRepairOrder();

    $this->post(route('portal.estimates.authorize', ['token' => portalAuthorizationPlainToken()]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [
            $recommendedConcern->id => RepairOrderConcernDisposition::Declined->value,
        ],
    ])->assertRedirect()
        ->assertSessionHas('portal_authorization');

    $recommendedConcern->refresh();

    expect($recommendedConcern->disposition)->toBe(RepairOrderConcernDisposition::Declined);

    $approval = ApprovalEvent::query()->sole();

    expect($approval)
        ->source->toBe(ApprovalSource::Portal)
        ->approval_type->toBe(ApprovalType::Partial)
        ->and($approval->approved_amount_cents)->toBe(0);

    expect(app(CustomerFacingEstimateStatus::class)
        ->labelForRepairOrder($repairOrder->fresh(['concerns'])))
        ->toBe('Declined');

    $event = CommunicationEvent::query()->sole();

    expect($event->summary)->toBe('Customer responded via portal (0 approved, 0 deferred, 1 declined).');
});

test('portal decline all does not show approved status on estimate document footer', function () {
    [$repairOrder, $token, $recommendedConcern] = portalAuthorizationRepairOrder();

    $this->post(route('portal.estimates.authorize', ['token' => portalAuthorizationPlainToken()]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [
            $recommendedConcern->id => RepairOrderConcernDisposition::Declined->value,
        ],
    ])->assertRedirect();

    $snapshot = app(EstimateSnapshotBuilder::class)
        ->build($repairOrder->fresh(['concerns.lines', 'approvalEvents']));
    $footer = app(DocumentFooterPresenter::class)->present($snapshot);

    expect($footer['approval']['status_label'])->toBe('Declined')
        ->and($footer['approval']['status'])->toBe('declined')
        ->and($footer['approval']['approved_by'])->toBe('Morgan Brown');
});

test('portal decline all keeps review posture on declined work not approved', function () {
    [$repairOrder, $token, $recommendedConcern] = portalAuthorizationRepairOrder();

    $this->post(route('portal.estimates.authorize', ['token' => portalAuthorizationPlainToken()]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [
            $recommendedConcern->id => RepairOrderConcernDisposition::Declined->value,
        ],
    ])->assertRedirect();

    $posture = RepairOrderPosture::for($repairOrder->fresh(['concerns', 'approvalEvents']));

    expect($posture['approvalPosture'])->toBe('Work declined')
        ->and($posture['approvedConcerns'])->toHaveCount(0)
        ->and($posture['declinedConcerns'])->toHaveCount(1)
        ->and($posture['deferredConcerns'])->toHaveCount(0);

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue();
});

test('portal estimate authorization form shows decline option', function () {
    portalAuthorizationRepairOrder();

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk()
        ->assertSee('Decline', false)
        ->assertSee('Defer', false)
        ->assertSee('Approve', false);
});

test('portal defer all does not show approved status on estimate document footer', function () {
    [$repairOrder, $token, $recommendedConcern] = portalAuthorizationRepairOrder();

    $this->post(route('portal.estimates.authorize', ['token' => portalAuthorizationPlainToken()]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [
            $recommendedConcern->id => RepairOrderConcernDisposition::Deferred->value,
        ],
    ])->assertRedirect();

    $snapshot = app(EstimateSnapshotBuilder::class)
        ->build($repairOrder->fresh(['concerns.lines', 'approvalEvents']));
    $footer = app(DocumentFooterPresenter::class)->present($snapshot);

    expect($footer['approval']['status_label'])->toBe('Deferred')
        ->and($footer['approval']['status'])->toBe('deferred')
        ->and($footer['approval']['approved_by'])->toBe('Morgan Brown');
});

test('portal defer all keeps review posture on deferred work not approved', function () {
    [$repairOrder, $token, $recommendedConcern] = portalAuthorizationRepairOrder();

    $this->post(route('portal.estimates.authorize', ['token' => portalAuthorizationPlainToken()]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [
            $recommendedConcern->id => RepairOrderConcernDisposition::Deferred->value,
        ],
    ])->assertRedirect();

    $posture = RepairOrderPosture::for($repairOrder->fresh(['concerns', 'approvalEvents']));

    expect($posture['approvalPosture'])->toBe('Deferred work retained')
        ->and($posture['approvedConcerns'])->toHaveCount(0)
        ->and($posture['deferredConcerns'])->toHaveCount(1);

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue();
});

test('portal estimate records estimate viewed communication event once', function () {
    [$repairOrder, $token] = portalAuthorizationRepairOrder();

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk()
        ->assertSee('Choose what to approve');

    expect(CommunicationEvent::query()->count())->toBe(1);

    $event = CommunicationEvent::query()->sole();

    expect($event->event_type)->toBe(OperationalCommunicationType::EstimateViewed)
        ->and($event->channel)->toBe(OperationalCommunicationChannel::Website)
        ->and($event->conversation_message_id)->not->toBeNull();

    $message = ConversationMessage::query()->sole();

    expect($message->body)->toBe('Customer opened the estimate portal link.')
        ->and($message->channel)->toBe(OperationalCommunicationChannel::Website)
        ->and($message->direction)->toBe(OperationalCommunicationDirection::Inbound);

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk();

    expect(CommunicationEvent::query()->count())->toBe(1)
        ->and(ConversationMessage::query()->count())->toBe(1);
});

test('staff portal preview uses the trust customer footer', function () {
    [$repairOrder] = portalAuthorizationRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.portal-preview', $repairOrder))
        ->assertOk()
        ->assertSee('Staff preview', false)
        ->assertSee('customer-footer', false)
        ->assertSee('customer-footer__grid', false)
        ->assertDontSee('Why customers choose us', false)
        ->assertDontSee('customer-footer__columns', false);
});

test('staff portal preview does not record estimate viewed or touch last viewed timestamp', function () {
    [$repairOrder, $token] = portalAuthorizationRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.portal-preview', $repairOrder))
        ->assertOk()
        ->assertSee('Staff preview');

    expect(CommunicationEvent::query()->count())->toBe(0)
        ->and($token->fresh()->last_viewed_at)->toBeNull()
        ->and(ConversationMessage::query()->count())->toBe(0);
});

test('authenticated advisor opening customer portal link does not record estimate viewed', function () {
    [$repairOrder, $token] = portalAuthorizationRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk();

    expect(CommunicationEvent::query()->count())->toBe(0)
        ->and($token->fresh()->last_viewed_at)->toBeNull();
});

test('customer portal open after advisor preview still records estimate viewed once', function () {
    [$repairOrder, $token] = portalAuthorizationRepairOrder();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.portal-preview', $repairOrder))
        ->assertOk();

    auth()->logout();

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk();

    expect(CommunicationEvent::query()->count())->toBe(1)
        ->and(ConversationMessage::query()->count())->toBe(1);

    $event = CommunicationEvent::query()->sole();

    expect($event->event_type)->toBe(OperationalCommunicationType::EstimateViewed)
        ->and($event->channel)->toBe(OperationalCommunicationChannel::Website)
        ->and($event->conversation_message_id)->not->toBeNull()
        ->and($token->fresh()->last_viewed_at)->not->toBeNull();
});

test('portal estimate deposit completes after authorization', function () {
    fakeHostedPlatformPaymentCapture();

    [$repairOrder, $token, $recommendedConcern] = portalAuthorizationRepairOrder();

    $this->post(route('portal.estimates.authorize', ['token' => portalAuthorizationPlainToken()]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [
            $recommendedConcern->id => RepairOrderConcernDisposition::Approved->value,
        ],
    ])->assertRedirect();

    $approval = ApprovalEvent::query()->sole();

    $initiate = $this->postJson(route('portal.estimates.deposits.store', ['token' => portalAuthorizationPlainToken()]), [
        'approval_id' => $approval->id,
    ])->assertOk();

    $attemptId = $initiate->json('attempt.id');

    $this->postJson(route('portal.estimates.deposits.complete', [
        'token' => portalAuthorizationPlainToken(),
        'attempt' => $attemptId,
    ]), [
        'source_id' => 'cnon:portal-deposit',
    ])->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Completed->value)
        ->assertJsonPath('attempt.capture_surface', PaymentCaptureSurface::PortalEstimateDeposit->value);

    expect(RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Deposit)
        ->exists())->toBeTrue();
});

test('portal estimate still collects remaining balance after a deposit is on file', function () {
    fakeHostedPlatformPaymentCapture();

    ShopSettings::current()->update([
        'default_deposit_enabled' => true,
        'default_deposit_include_parts' => true,
        'default_deposit_include_diagnostics' => false,
        'shop_fee_enabled' => false,
        'tax_enabled' => false,
    ]);

    [$repairOrder, $token, $recommendedConcern] = portalAuthorizationRepairOrder();

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $recommendedConcern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'A/C compressor',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
        'part_cost_cents' => 5000,
    ]);

    app(\App\Ark\Operations\Financial\EstimateTotalsCalculator::class)->recalculateRepairOrder($repairOrder->fresh());

    $this->post(route('portal.estimates.authorize', ['token' => portalAuthorizationPlainToken()]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [
            $recommendedConcern->id => RepairOrderConcernDisposition::Approved->value,
        ],
    ])->assertRedirect();

    $approval = ApprovalEvent::query()->sole();

    $first = $this->postJson(route('portal.estimates.deposits.store', ['token' => portalAuthorizationPlainToken()]), [
        'approval_id' => $approval->id,
    ])->assertOk();

    $this->postJson(route('portal.estimates.deposits.complete', [
        'token' => portalAuthorizationPlainToken(),
        'attempt' => $first->json('attempt.id'),
    ]), [
        'source_id' => 'cnon:portal-deposit',
    ])->assertOk();

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk()
        ->assertSee('Pay remaining balance')
        ->assertSee('Pay remaining')
        ->assertSee('Partial payment received')
        ->assertSee('remaining')
        ->assertDontSee('has your approval and payment')
        ->assertDontSee('The shop has been notified')
        ->assertDontSee('A deposit has already been collected');

    $second = $this->postJson(route('portal.estimates.deposits.store', ['token' => portalAuthorizationPlainToken()]), [
        'approval_id' => $approval->id,
    ])->assertOk();

    expect((int) $second->json('attempt.amount_cents'))->toBeGreaterThan(0)
        ->and((int) $second->json('attempt.amount_cents'))->not->toBe((int) $first->json('attempt.amount_cents'));

    $this->postJson(route('portal.estimates.deposits.complete', [
        'token' => portalAuthorizationPlainToken(),
        'attempt' => $second->json('attempt.id'),
    ]), [
        'source_id' => 'cnon:portal-remaining',
    ])->assertOk()
        ->assertJsonPath('attempt.status', PaymentGatewayAttemptStatus::Completed->value);

    expect(RepairOrderLedgerEntry::query()
        ->where('repair_order_id', $repairOrder->id)
        ->where('entry_type', LedgerEntryType::Deposit)
        ->count())->toBe(2);

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk()
        ->assertSee('Payment received')
        ->assertSee('we received your')
        ->assertSee('has your approval and payment')
        ->assertDontSee('The shop has been notified')
        ->assertDontSee('Partial payment received');
});

test('portal estimate does not claim payment received when an invoice is issued without ledger money', function () {
    [$repairOrder, $token, $recommendedConcern] = portalAuthorizationRepairOrder();

    $this->post(route('portal.estimates.authorize', ['token' => portalAuthorizationPlainToken()]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [
            $recommendedConcern->id => RepairOrderConcernDisposition::Approved->value,
        ],
    ])->assertRedirect();

    issueFinalInvoiceFor($repairOrder->fresh());

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk()
        ->assertSee('You’re all set')
        ->assertSee('Balance due')
        ->assertDontSee('has your approval and payment')
        ->assertDontSee('The shop has been notified')
        ->assertDontSee('Partial payment received');
});

test('portal estimate reflects a partial invoice payment then paid in full', function () {
    [$repairOrder, $token, $recommendedConcern] = portalAuthorizationRepairOrder();

    $this->post(route('portal.estimates.authorize', ['token' => portalAuthorizationPlainToken()]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [
            $recommendedConcern->id => RepairOrderConcernDisposition::Approved->value,
        ],
    ])->assertRedirect();

    issueFinalInvoiceFor($repairOrder->fresh());

    app(RecordLedgerEntryAction::class)->recordPayment(
        $repairOrder->fresh(),
        100,
        PaymentMethod::Cash,
    );

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk()
        ->assertSee('Partial payment received')
        ->assertSee('$1.00')
        ->assertSee('remaining')
        ->assertDontSee('has your approval and payment')
        ->assertDontSee('The shop has been notified');

    payRepairOrderInFull($repairOrder->fresh());

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk()
        ->assertSee('Payment received')
        ->assertSee('we received your')
        ->assertSee('has your approval and payment')
        ->assertDontSee('The shop has been notified')
        ->assertDontSee('Balance due $', false);
});

test('portal estimate deposit initiate returns json when portal pay is disabled', function () {
    config()->set('services.square.application_id', 'sq0idp-test-app');
    config()->set('services.square.access_token', 'test-token');
    config()->set('services.square.location_id', 'LOC123');
    config()->set('services.square.webhook_signature_key', 'test-signature-key');

    ShopSettings::current()->update([
        'square_enabled' => true,
        'square_portal_pay_enabled' => false,
    ]);

    [$repairOrder, $token, $recommendedConcern] = portalAuthorizationRepairOrder();

    $this->post(route('portal.estimates.authorize', ['token' => portalAuthorizationPlainToken()]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [
            $recommendedConcern->id => RepairOrderConcernDisposition::Approved->value,
        ],
    ])->assertRedirect();

    $approval = ApprovalEvent::query()->sole();

    $this->postJson(route('portal.estimates.deposits.store', ['token' => portalAuthorizationPlainToken()]), [
        'approval_id' => $approval->id,
    ])->assertStatus(503)
        ->assertJsonPath('message', 'Online deposits are not enabled.');

    ShopSettings::current()->update([
        'square_enabled' => true,
        'square_portal_pay_enabled' => true,
    ]);

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk()
        ->assertDontSee('Step 3 - Pay deposit');

    $this->postJson(route('portal.estimates.deposits.store', ['token' => portalAuthorizationPlainToken()]), [
        'approval_id' => $approval->id,
    ])->assertStatus(503)
        ->assertJsonPath('message', 'Online deposits are not enabled.');
});

test('staff portal preview disables live card deposit', function () {
    fakeHostedPlatformPaymentCapture();

    [$repairOrder, $token, $recommendedConcern] = portalAuthorizationRepairOrder();

    $this->post(route('portal.estimates.authorize', ['token' => portalAuthorizationPlainToken()]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [
            $recommendedConcern->id => RepairOrderConcernDisposition::Approved->value,
        ],
    ])->assertRedirect();

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.repair-orders.portal-preview', $repairOrder))
        ->assertOk()
        ->assertSee('Step 3 - Pay deposit')
        ->assertSee('Card deposit is disabled in staff preview')
        ->assertDontSee('arkPortalEstimateDeposit', false);
});

test('portal estimate deposit complete url keeps zeros inside the access token', function () {
    fakeHostedPlatformPaymentCapture();

    [$repairOrder, $tokenModel, $recommendedConcern] = portalAuthorizationRepairOrder();

    $plainToken = 'abc0def0'.str_repeat('a', 56);

    $tokenModel->forceFill([
        'token_hash' => EstimateAccessToken::hashPlainToken($plainToken),
    ])->save();

    $this->post(route('portal.estimates.authorize', ['token' => $plainToken]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [
            $recommendedConcern->id => RepairOrderConcernDisposition::Approved->value,
        ],
    ])->assertRedirect();

    $html = $this->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertSee('Step 3 - Pay deposit', false)
        ->getContent();

    // @js() escapes path slashes as \/
    expect($html)->toContain('abc0def0')
        ->and($html)->toContain('__ATTEMPT__\\/complete')
        ->and($html)->not->toContain('abc__ATTEMPT__def__ATTEMPT__');
});

test('portal estimate deposit panel persists after session flash expires', function () {
    fakeHostedPlatformPaymentCapture();

    [$repairOrder, $token, $recommendedConcern] = portalAuthorizationRepairOrder();

    $this->post(route('portal.estimates.authorize', ['token' => portalAuthorizationPlainToken()]), [
        'confirmed_name' => 'Morgan Brown',
        'concern_dispositions' => [
            $recommendedConcern->id => RepairOrderConcernDisposition::Approved->value,
        ],
    ])->assertRedirect();

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk()
        ->assertSee('Next step: pay your deposit')
        ->assertSee('Step 3 - Pay deposit')
        ->assertSee('Paying the deposit does not approve any extra repairs');

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk()
        ->assertSee('Next step: pay your deposit')
        ->assertSee('Step 3 - Pay deposit');
});

test('portal estimate shows authorize instructions when deposit is enabled', function () {
    fakeHostedPlatformPaymentCapture();

    portalAuthorizationRepairOrder();

    $this->get(route('portal.estimates.show', ['token' => portalAuthorizationPlainToken()]))
        ->assertOk()
        ->assertSee('How to approve your estimate')
        ->assertSee('pay it on the next step')
        ->assertSee('Pay deposit')
        ->assertSee('portal-estimate-stepper--4', false)
        ->assertSee('--portal-estimate-steps: 4', false);
});

test('estimate email includes portal review link', function () {
    Mail::fake();
    Storage::fake('local');

    $this->app->bind(PdfRenderer::class, function (): PdfRenderer {
        return new class implements PdfRenderer
        {
            public function renderEstimate(EstimateDocument $document): string
            {
                $path = 'estimate-documents/ro-'.$document->repair_order_id.'/current-estimate.pdf';
                Storage::disk('local')->put($path, 'PDF');

                $document->forceFill([
                    'status' => 'generated',
                    'pdf_path' => $path,
                    'generated_at' => now(),
                    'needs_pdf_refresh' => false,
                    'pdf_refreshed_at' => now(),
                ])->save();

                return $path;
            }
        };
    });

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = portalAuthorizationRepairOrder()[0];

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.estimate.email', $repairOrder), [
            'message' => 'Please review online.',
            'acknowledge_missing_vin' => true,
        ])
        ->assertRedirect();

    Mail::assertSent(EstimateCustomerMail::class, function (EstimateCustomerMail $mail): bool {
        return str_contains($mail->portalUrl, '/portal/estimates/')
            && $mail->hasTo('customer@example.test');
    });

    expect(EstimateAccessToken::query()->where('repair_order_id', $repairOrder->id)->exists())->toBeTrue();
});

/**
 * @return array{0: RepairOrder, 1: EstimateAccessToken, 2: RepairOrderConcern}
 */
function portalAuthorizationRepairOrder(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Morgan',
        'last_name' => 'Brown',
        'phone' => '555-0144',
        'email' => 'customer@example.test',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2013,
        'make' => 'Chevrolet',
        'model' => 'Tahoe',
        'vin' => '1GNSKBE0XDR000001',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'A/C not cold',
    ]);

    $recommendedConcern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'A/C not cold',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $recommendedConcern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'A/C performance diagnostic',
        'quantity' => '1.00',
        'unit_price_cents' => 15593,
    ]);

    $token = EstimateAccessToken::createForPlainToken($repairOrder, portalAuthorizationPlainToken());

    return [$repairOrder->fresh(['customer', 'vehicle', 'concerns.lines']), $token, $recommendedConcern];
}

function portalAuthorizationPlainToken(): string
{
    return str_repeat('c', 64);
}
