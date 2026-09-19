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
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function bindFakeEstimatePdfRenderer(): void
{
    Storage::fake('local');

    app()->bind(PdfRenderer::class, function (): PdfRenderer {
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
}

test('advisor can email customer the current estimate pdf', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    bindFakeEstimatePdfRenderer();
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

test('hosted estimate email uses platform mail and ignores leftover core postmark', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    bindFakeEstimatePdfRenderer();
    enableHostedPlatformMail();
    fakeHostedPlatformMail();
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

    Mail::assertNothingSent();

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://cloud.test/api/v1/services/mail/messages/transactional'
            && $request->method() === 'POST'
            && ($body['operation'] ?? null) === 'estimate.send'
            && ($body['to'] ?? null) === 'customer@example.test'
            && str_contains((string) ($body['subject'] ?? ''), 'estimate')
            && filled($body['html_body'] ?? null)
            && ($body['attachments'][0]['mime'] ?? null) === 'application/pdf'
            && ($body['attachments'][0]['content_base64'] ?? null) === base64_encode('PDF')
            && ! array_key_exists('postmark_server_token', $body)
            && ! array_key_exists('from', $body)
            && ! array_key_exists('reply_to', $body);
    });

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'postmark'));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/starter/'));

    expect(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::EstimateSent)->exists())->toBeTrue()
        ->and($repairOrder->fresh()->communicationPostureLabel())->toBe('Estimate sent · Email');
});

test('hosted estimate email does not fall back to laravel mail when platform rejects', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    bindFakeEstimatePdfRenderer();
    enableHostedPlatformMail();
    Mail::fake();
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/api/v1/services/mail/messages/transactional')) {
            return Http::response([
                'ok' => false,
                'reason_code' => 'quota_exceeded',
                'message' => 'Monthly included send allowance exceeded.',
            ], 422);
        }

        return Http::response(['unexpected' => $request->url()], 599);
    });

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.estimate.email', $repairOrder), [
            'message' => 'Please review the attached estimate today.',
        ])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder))
        ->assertSessionHasErrors('email');

    Mail::assertNothingSent();
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'postmark'));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/starter/'));

    expect(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::EstimateSent)->exists())->toBeFalse();
});

test('hosted send estimate rail emails through platform', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    bindFakeEstimatePdfRenderer();
    enableHostedPlatformMail();
    fakeHostedPlatformMail();
    Mail::fake();

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::Estimate);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.conversation-actions.send-estimate', $repairOrder), [
            'delivery' => 'email',
        ])
        ->assertOk();

    Mail::assertNothingSent();
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://cloud.test/api/v1/services/mail/messages/transactional'
        && ($request->data()['operation'] ?? null) === 'estimate.send');
});

test('estimate email reuses an existing pdf instead of regenerating', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    Storage::fake('local');
    $renders = (object) ['count' => 0];

    app()->bind(PdfRenderer::class, function () use ($renders): PdfRenderer {
        return new class($renders) implements PdfRenderer
        {
            public function __construct(private object $renders) {}

            public function renderEstimate(EstimateDocument $document): string
            {
                $this->renders->count++;
                $path = 'estimate-documents/ro-'.$document->repair_order_id.'/current-estimate.pdf';
                Storage::disk('local')->put($path, 'PDF');
                $document->forceFill([
                    'status' => 'generated',
                    'pdf_path' => $path,
                    'generated_at' => now(),
                    'needs_pdf_refresh' => true,
                    'pdf_refreshed_at' => now(),
                ])->save();

                return $path;
            }
        };
    });

    enableHostedPlatformMail();
    fakeHostedPlatformMail();
    Mail::fake();

    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = repairOrderForCommunication(status: RepairOrderStatus::WaitingApproval);

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.estimate.email', $repairOrder), [])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder));

    expect($renders->count)->toBe(1);

    fakeHostedPlatformMail();

    $this->actingAs($advisor)
        ->from(route('operations.repair-orders.show', $repairOrder))
        ->post(route('operations.repair-orders.estimate.email', $repairOrder), [])
        ->assertRedirect(route('operations.repair-orders.show', $repairOrder));

    expect($renders->count)->toBe(1);
});
