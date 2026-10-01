<?php

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Portal\CreateOrReuseEstimateAccessTokenAction;
use App\Ark\Operations\Portal\PortalCustomerViewGate;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
});

test('portal customer view gate blocks known sms preview user agents', function (string $userAgent) {
    $request = Request::create('/portal/estimates/token', 'GET');
    $request->headers->set('User-Agent', $userAgent);

    expect(PortalCustomerViewGate::shouldRecordCustomerView($request))->toBeFalse();
})->with([
    'WhatsApp' => 'WhatsApp/2.23.0',
    'Facebook' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
    'Twitter' => 'Twitterbot/1.0',
    'Slack' => 'Slackbot-LinkExpanding 1.0',
    'Telegram' => 'TelegramBot (like TwitterBot)',
    'Applebot' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1.1 Safari/605.1.15 (Applebot/0.1)',
    'Skype preview' => 'Mozilla/5.0 SkypeUriPreview Preview/0.5',
    // iMessage on-device preview: Safari-shaped UA + social crawler tokens (not Applebot).
    'iMessage preview' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/601.2.4 (KHTML, like Gecko) Version/9.0.1 Safari/601.2.4 facebookexternalhit/1.1 Facebot Twitterbot/1.0',
    'empty UA' => '',
]);

test('portal customer view gate blocks navigate with non-user sec-fetch-user value', function () {
    $request = Request::create('/portal/estimates/token', 'GET');
    $request->headers->set(
        'User-Agent',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
    );
    $request->headers->set('Sec-Fetch-Mode', 'navigate');
    $request->headers->set('Sec-Fetch-Dest', 'document');
    $request->headers->set('Sec-Fetch-User', '?0');

    expect(PortalCustomerViewGate::shouldRecordCustomerView($request))->toBeFalse();
});

test('portal customer view gate blocks purpose prefetch and preview headers', function () {
    $request = Request::create('/portal/estimates/token', 'GET');
    $request->headers->set('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15');
    $request->headers->set('Purpose', 'prefetch');

    expect(PortalCustomerViewGate::shouldRecordCustomerView($request))->toBeFalse();

    $preview = Request::create('/portal/estimates/token', 'GET');
    $preview->headers->set('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15');
    $preview->headers->set('Sec-Purpose', 'preview');

    expect(PortalCustomerViewGate::shouldRecordCustomerView($preview))->toBeFalse();
});

test('portal customer view gate allows normal customer browser navigation', function () {
    $request = Request::create('/portal/estimates/token', 'GET');
    $request->headers->set(
        'User-Agent',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
    );
    $request->headers->set('Sec-Fetch-Mode', 'navigate');
    $request->headers->set('Sec-Fetch-User', '?1');
    $request->headers->set('Sec-Fetch-Dest', 'document');

    expect(PortalCustomerViewGate::shouldRecordCustomerView($request))->toBeTrue();
});

test('sms preview user agent does not record estimate viewed', function (string $userAgent) {
    [$repairOrder, $plainToken] = portalViewGateEstimate();

    $this->withHeaders([
        'User-Agent' => $userAgent,
    ])->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk();

    expect(CommunicationEvent::query()->count())->toBe(0)
        ->and(ConversationMessage::query()->count())->toBe(0)
        ->and($repairOrder->estimateAccessTokens()->first()?->fresh()->last_viewed_at)->toBeNull();
})->with([
    'Facebook crawler' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
    'iMessage preview' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/601.2.4 (KHTML, like Gecko) Version/9.0.1 Safari/601.2.4 facebookexternalhit/1.1 Facebot Twitterbot/1.0',
]);

test('real customer browser still records estimate viewed after preview bot', function () {
    [$repairOrder, $plainToken] = portalViewGateEstimate();

    $this->withHeaders([
        'User-Agent' => 'Twitterbot/1.0',
    ])->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk();

    expect(CommunicationEvent::query()->count())->toBe(0);

    $this->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
        'Sec-Fetch-Mode' => 'navigate',
        'Sec-Fetch-User' => '?1',
        'Sec-Fetch-Dest' => 'document',
    ])->get(route('portal.estimates.show', ['token' => $plainToken]))
        ->assertOk();

    expect(CommunicationEvent::query()->where('event_type', OperationalCommunicationType::EstimateViewed)->count())->toBe(1)
        ->and($repairOrder->estimateAccessTokens()->first()?->fresh()->last_viewed_at)->not->toBeNull();
});

/**
 * @return array{0: RepairOrder, 1: string}
 */
function portalViewGateEstimate(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Morgan',
        'last_name' => 'Brown',
        'phone' => '7195557788',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2019,
        'make' => 'Toyota',
        'model' => 'Camry',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::WaitingApproval,
        'repair_order_id' => random_int(9200, 9299),
        'concern_summary' => 'Brakes',
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brake pads',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $access = app(CreateOrReuseEstimateAccessTokenAction::class)->execute($repairOrder);

    return [$repairOrder->fresh(), $access->plainToken];
}
