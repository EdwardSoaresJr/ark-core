<?php

use App\Ark\Operations\Approvals\ApprovalEvent;
use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Portal\EstimateAccessToken;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['portal_signature_required' => false]);
});

test('emailing estimate moves repair order to awaiting approval', function () {
    Mail::fake();
    Storage::fake('local');

    $this->app->bind(\App\Ark\Operations\Documents\PdfRenderer::class, function (): \App\Ark\Operations\Documents\PdfRenderer {
        return new class implements \App\Ark\Operations\Documents\PdfRenderer
        {
            public function renderEstimate(\App\Ark\Operations\Documents\EstimateDocument $document): string
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
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::Estimate);

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.estimate.email', $repairOrder), [
            'message' => 'Please review online.',
        ])
        ->assertRedirect();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue();
});

test('texting estimate link moves repair order to awaiting approval and logs estimate sent', function () {
    bindFakeOutboundSms();

        
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);

    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::Estimate);
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();

    \App\Ark\Operations\Messaging\PhoneSmsCapability::query()->updateOrCreate(
        ['normalized_phone' => \App\Ark\Operations\PhoneNumber::normalize('7195558080')],
        [
            'valid' => true,
            'line_type' => 'mobile',
            'carrier_name' => 'Test',
            'sms_capable' => true,
            'reason' => null,
            'checked_at' => now(),
        ],
    );

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder))
        ->assertOk()
        ->assertJsonPath('awaiting_approval.moved', true)
        ->assertJsonPath('awaiting_approval.reason', 'moved');

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue();

    expect(CommunicationEvent::query()
        ->where('event_type', OperationalCommunicationType::EstimateSent)
        ->exists())->toBeTrue();
});

test('portal authorization advances repair order toward production', function () {
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);
    $concern = $repairOrder->concerns()->firstOrFail();

    $plainToken = str_repeat('f', 64);
    EstimateAccessToken::createForPlainToken($repairOrder, $plainToken);

    $this->post(route('portal.estimates.authorize', ['token' => $plainToken]), [
        'confirmed_name' => 'Comm Customer',
        'concern_dispositions' => [
            $concern->id => RepairOrderConcernDisposition::Approved->value,
        ],
    ])->assertRedirect();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::ReadyForWork))->toBeTrue();
});

test('advisor can copy estimate portal link without sending sms', function () {
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::Estimate);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->getJson(route('operations.repair-orders.estimate-portal-link', $repairOrder))
        ->assertOk()
        ->assertJsonStructure(['url', 'token_reused'])
        ->assertJsonPath('token_reused', false);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->getJson(route('operations.repair-orders.estimate-portal-link', $repairOrder))
        ->assertOk()
        ->assertJsonPath('token_reused', true);
});

test('portal authorization requires signature when shop setting enabled', function () {
    ShopSettings::current()->update(['portal_signature_required' => true]);

    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);
    $concern = $repairOrder->concerns()->firstOrFail();

    $plainToken = str_repeat('g', 64);
    EstimateAccessToken::createForPlainToken($repairOrder, $plainToken);

    $this->post(route('portal.estimates.authorize', ['token' => $plainToken]), [
        'confirmed_name' => 'Comm Customer',
        'concern_dispositions' => [
            $concern->id => RepairOrderConcernDisposition::Approved->value,
        ],
    ])->assertSessionHasErrors(['signature_data', 'authorization_acknowledged']);

    $signature = 'data:image/png;base64,'.base64_encode(base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));

    $this->post(route('portal.estimates.authorize', ['token' => $plainToken]), [
        'confirmed_name' => 'Comm Customer',
        'concern_dispositions' => [
            $concern->id => RepairOrderConcernDisposition::Approved->value,
        ],
        'authorization_acknowledged' => '1',
        'signature_data' => $signature,
    ])->assertRedirect()
        ->assertSessionHas('portal_authorization');

    $approval = ApprovalEvent::query()->sole();

    expect($approval->signature_path)->not->toBeNull()
        ->and($approval->source)->toBe(ApprovalSource::Portal);
});
