<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Mail\EstimateCustomerMail;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
        
    \App\Ark\Operations\Settings\ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
    ]);
});

test('estimate email with missing vin override records operational fact', function () {
    Storage::fake('local');
    Mail::fake();

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
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);
    $repairOrder->vehicle->forceFill(['vin' => null, 'normalized_vin' => null])->save();

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.estimate.email', $repairOrder), [
            'message' => 'Please review today.',
            'acknowledge_missing_vin' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    Mail::assertSent(EstimateCustomerMail::class);

    $event = OperationalEvent::query()
        ->where('event_name', OperationalEventName::EstimateSentWithMissingVin->value)
        ->sole();

    expect($event->aggregate_id)->toBe($repairOrder->id)
        ->and($event->actor_user_id)->toBe($advisor->id)
        ->and($event->occurred_at)->not->toBeNull()
        ->and($event->payload_json)->toMatchArray([
            'repair_order_id' => $repairOrder->id,
            'vehicle_id' => $repairOrder->vehicle_id,
            'customer_id' => $repairOrder->customer_id,
            'channel' => 'email',
        ]);
});

test('estimate email without missing vin override does not record override fact', function () {
    Storage::fake('local');
    Mail::fake();

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
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.estimate.email', $repairOrder), [
            'message' => 'Please review today.',
        ])
        ->assertRedirect();

    expect(OperationalEvent::query()
        ->where('event_name', OperationalEventName::EstimateSentWithMissingVin->value)
        ->count())->toBe(0);
});

test('send estimate sms with missing vin override records operational fact', function () {
    bindFakeOutboundSms();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = missingVinEstimateRepairOrder();

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder), [
            'acknowledge_missing_vin' => true,
        ])
        ->assertOk();

    $event = OperationalEvent::query()
        ->where('event_name', OperationalEventName::EstimateSentWithMissingVin->value)
        ->sole();

    expect($event->payload_json['channel'])->toBe('portal')
        ->and($event->payload_json['repair_order_id'])->toBe($repairOrder->id);
});

function missingVinEstimateRepairOrder(): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Override',
        'last_name' => 'Fact',
        'phone' => '7195558800',
        'customer_type' => 'Retail',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2014,
        'make' => 'Jeep',
        'model' => 'Wrangler',
        'plate' => 'OVRRDE',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'concern_summary' => 'Noise on acceleration',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Diagnose noise',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    return $repairOrder;
}
