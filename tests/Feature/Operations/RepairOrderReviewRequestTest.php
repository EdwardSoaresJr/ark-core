<?php

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\PdfRenderer;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\Messaging\ReviewRequestAuthority;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Mail\ReviewRequestCustomerMail;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->app->bind(PdfRenderer::class, ReviewRequestFakePdfRenderer::class);

    bindFakeOutboundSms();

    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
        'shop_name' => 'Demo Auto Repair',
        'shop_timezone' => 'America/Denver',
    ]);
    config()->set('app.timezone', 'UTC');

    ShopSettings::current()->update([
        'google_reviews_url' => 'https://example.test/google-review',
    ]);
});

function seedReviewRequestSmsCapable(string $phone = '7195558080'): void
{
    PhoneSmsCapability::query()->updateOrCreate(
        ['normalized_phone' => preg_replace('/\D+/', '', $phone)],
        [
            'valid' => true,
            'line_type' => 'mobile',
            'carrier_name' => 'Test',
            'sms_capable' => true,
            'reason' => null,
            'checked_at' => now(),
            'raw_payload' => ['source' => 'test'],
        ],
    );
}

test('paid close no longer requires asked-for-review bookkeeping', function (): void {
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    $repairOrder->forceFill(['mileage_out' => 88000])->save();
    issueFinalInvoiceFor($repairOrder->fresh());
    payRepairOrderInFull($repairOrder->fresh());

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder->fresh()), [
        'status' => 'closed:paid',
    ])->assertRedirect()
        ->assertSessionHasNoErrors();

    $repairOrder->refresh();

    expect($repairOrder->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and($repairOrder->close_variant_key)->toBe('paid')
        ->and($repairOrder->review_request_sent)->toBeNull();
});

test('review request text sends once and records outbound authority', function (): void {
    bindFakeOutboundSms();
    seedReviewRequestSmsCapable();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    $repairOrder->customer->forceFill(['phone' => '7195558080', 'email' => null])->save();
    $repairOrder->forceFill(['mileage_out' => 88000])->save();
    issueFinalInvoiceFor($repairOrder->fresh());
    payRepairOrderInFull($repairOrder->fresh());

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.review-request.send', $repairOrder->fresh()), [
            'delivery' => 'sms',
            'close_paid' => '1',
            RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $message = ConversationMessage::query()->sole();
    $repairOrder->refresh();

    expect($message->channel)->toBe(OperationalCommunicationChannel::Sms)
        ->and($message->body)->toContain('https://example.test/google-review')
        ->and($message->body)->toContain('Thank you for choosing Demo Auto Repair!')
        ->and($message->body)->toContain("we'd be grateful if you could take a moment")
        ->and($message->body)->toContain('Leave a Google Review:')
        ->and($message->body)->toContain(route('portal.access'))
        ->and($message->metadata['kind'])->toBe(ReviewRequestAuthority::METADATA_KIND)
        ->and($repairOrder->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and($repairOrder->review_request_sent)->toBeTrue()
        ->and($repairOrder->review_request_recorded_by)->toBe($advisor->id);

    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMreview02',
            'status' => 'queued',
        ], 201),
    ]);
    bindFakeOutboundSms();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.review-request.send', $repairOrder->fresh()), [
            'delivery' => 'sms',
            RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(ConversationMessage::query()->count())->toBe(1);
});

test('review request email sends once', function (): void {
    Mail::fake();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    $repairOrder->customer->forceFill([
        'phone' => null,
        'email' => 'customer@example.test',
    ])->save();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.review-request.send', $repairOrder->fresh()), [
            'delivery' => 'email',
            RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Mail::assertSent(ReviewRequestCustomerMail::class);
    expect(ConversationMessage::query()->sole()->channel)->toBe(OperationalCommunicationChannel::Email)
        ->and(ConversationMessage::query()->sole()->metadata['kind'])->toBe(ReviewRequestAuthority::METADATA_KIND);
});

test('review request text and email both send once', function (): void {
    Mail::fake();
    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMreviewBoth',
            'status' => 'queued',
        ], 201),
    ]);
    bindFakeOutboundSms();
    seedReviewRequestSmsCapable();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    $repairOrder->customer->forceFill([
        'phone' => '7195558080',
        'email' => 'customer@example.test',
    ])->save();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.review-request.send', $repairOrder->fresh()), [
            'delivery' => 'both',
            RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Mail::assertSent(ReviewRequestCustomerMail::class);
    expect(ConversationMessage::query()->count())->toBe(2)
        ->and(ConversationMessage::query()->pluck('channel')->map->value->sort()->values()->all())
        ->toBe(['email', 'sms']);
});

test('review request with no contact method does not send', function (): void {
    Http::fake();
    Mail::fake();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    $repairOrder->customer->forceFill([
        'phone' => null,
        'email' => null,
    ])->save();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.review-request.send', $repairOrder->fresh()), [
            'delivery' => 'sms',
            RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('review_request');

    expect(ConversationMessage::query()->count())->toBe(0);
    Mail::assertNothingSent();
    Http::assertNothingSent();
});

test('failed text send does not mark review request sent', function (): void {
    bindFailingOutboundSms('Outbound SMS failed.');
    seedReviewRequestSmsCapable();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    $repairOrder->customer->forceFill(['phone' => '7195558080'])->save();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.review-request.send', $repairOrder->fresh()), [
            'delivery' => 'sms',
            RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('review_request');

    $repairOrder->refresh();

    expect(ConversationMessage::query()->count())->toBe(0)
        ->and($repairOrder->review_request_sent)->toBeNull();
});

test('builder closeout shows send review actions instead of asked checkbox', function (): void {
    seedReviewRequestSmsCapable();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    $repairOrder->customer->forceFill([
        'phone' => '7195558080',
        'email' => 'customer@example.test',
    ])->save();
    $repairOrder->forceFill(['mileage_out' => 88000])->save();
    issueFinalInvoiceFor($repairOrder->fresh());
    payRepairOrderInFull($repairOrder->fresh());

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Thank you for choosing Demo Auto Repair!')
        ->assertSee("we'd be grateful if you could take a moment")
        ->assertSee('Leave a Google Review:')
        ->assertSee('Thank You for Choosing Demo Auto Repair')
        ->assertSee('The Demo Auto Repair Team')
        ->assertSee('opportunity to earn your business')
        ->assertSee('data-workspace-modal-form="review-request"', false)
        ->assertSee('name="delivery" value="sms"', false)
        ->assertSee('name="delivery" value="email"', false)
        ->assertSee('name="delivery" value="both"', false)
        ->assertSee('Not Now')
        ->assertSee('Preview')
        ->assertSee('https://example.test/google-review', false)
        ->assertSee(route('portal.access'), false)
        ->assertDontSee('No gating')
        ->assertDontSee('Same message for everyone')
        ->assertDontSee('Not editable here')
        ->assertDontSee("We'd love your honest feedback")
        ->assertDontSee('Was a Google review requested?')
        ->assertDontSee('Yes — review requested')
        ->assertDontSee('name="review_request_sent"', false);
});

test('review request when labels use shop timezone not app utc', function (): void {
    \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::parse('2026-08-08 22:00:00', 'UTC'));

    $at = \Illuminate\Support\Carbon::parse('2026-08-08 21:49:58', 'UTC');

    expect(app(ReviewRequestAuthority::class)->whenLabel($at))->toBe('Today 3:49 PM');

    \Illuminate\Support\Carbon::setTestNow();
});

test('sent review request presents as communication history', function (): void {
    Http::fake([
        'https://api.twilio.com/*' => Http::response([
            'sid' => 'SMreviewHistory',
            'status' => 'queued',
        ], 201),
    ]);
    bindFakeOutboundSms();
    seedReviewRequestSmsCapable();

    $advisor = User::factory()->create([
        'name' => 'Edward',
    ])->assignRole(ArkRole::Advisor->value);
    $repairOrder = financialCloseoutRepairOrder(RepairOrderStatus::ReadyPickup);
    $repairOrder->customer->forceFill(['phone' => '7195558080', 'email' => null])->save();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.review-request.send', $repairOrder->fresh()), [
            'delivery' => 'sms',
            RepairOrderConcurrency::FIELD => $repairOrder->fresh()->estimate_version,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder->fresh()))
        ->assertOk()
        ->assertSee('Review Requested', false)
        ->assertSee('Text · Today', false)
        ->assertSee('by Edward', false)
        ->assertDontSee('Request a Review', false)
        ->assertDontSee('name="delivery" value="sms"', false);
});

class ReviewRequestFakePdfRenderer implements PdfRenderer
{
    public function renderEstimate(EstimateDocument $document): string
    {
        $path = 'estimates/review-request-'.$document->id.'.pdf';

        $document->forceFill([
            'status' => 'generated',
            'pdf_path' => $path,
            'generated_at' => now(),
        ])->save();

        return $path;
    }
}
