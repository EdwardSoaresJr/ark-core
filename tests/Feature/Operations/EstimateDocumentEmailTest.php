<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Vehicles\VehicleIdentityPressure;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Mail\EstimateCustomerMail;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

test('advisor can email customer the current estimate pdf', function () {
    $this->seed(ArkAuthorizationSeeder::class);
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
    Mail::fake();

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.estimate.email', $repairOrder), [
            'message' => 'Please review the attached estimate today.',
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder))
        ->assertSessionHas('status');

    Mail::assertSent(EstimateCustomerMail::class, function (EstimateCustomerMail $mail) use ($repairOrder): bool {
        return $mail->hasTo('customer@example.test')
            && $mail->repairOrder->is($repairOrder)
            && $mail->staffNote === 'Please review the attached estimate today.'
            && str_contains($mail->portalUrl, '/portal/estimates/');
    });

    $event = CommunicationEvent::query()->sole();

    expect($event->event_type)->toBe(OperationalCommunicationType::EstimateSent)
        ->and($event->channel)->toBe(OperationalCommunicationChannel::Email)
        ->and($event->summary)->toContain('customer@example.test')
        ->and($event->conversation_message_id)->not->toBeNull()
        ->and($repairOrder->fresh()->communicationPostureLabel())->toBe('Estimate sent · Email');

    $event = OperationalEvent::query()
        ->where('event_name', OperationalEventName::EstimateEmailedToCustomer->value)
        ->sole();

    expect($event->payload_json)->toMatchArray([
        'repair_order_id' => $repairOrder->id,
        'recipient_email' => 'customer@example.test',
    ]);
});

test('estimate email blocks when vehicle vin is missing', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Mail::fake();

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);
    $repairOrder->vehicle->forceFill(['vin' => null, 'normalized_vin' => null])->save();

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.estimate.email', $repairOrder), [
            'message' => 'Please review today.',
        ])
        ->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});


test('estimate email requires a recipient address', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Mail::fake();

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::Estimate);
    $repairOrder->customer->forceFill(['email' => null])->save();

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.estimate.email', $repairOrder), [])
        ->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});

test('technician cannot email estimate to customer', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Mail::fake();

    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);

    $this->actingAs($technician)
        ->post(route('operations.repair-orders.estimate.email', $repairOrder))
        ->assertForbidden();

    Mail::assertNothingSent();
});
