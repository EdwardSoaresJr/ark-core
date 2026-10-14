<?php

use App\Ark\Operations\Communications\CommunicationsNavPressure;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionQueue;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Operations\Telephony\HostedCallOutcome;
use App\Ark\Operations\Telephony\TelephonyProviderType;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Carbon;

/**
 * Acceptance standard for "Needs attention".
 *
 * Needs attention means a real, unresolved staff action is required. It does
 * not mean the shop was the last party to communicate.
 *
 * The inbox count and the sidebar badge must use that same membership. The
 * badge may show distinct customers only when it is labeled as customers.
 * Until that label exists, the two counts are equal.
 *
 * A staff reply does not clear a customer question by itself. Clearing takes
 * an explicit resolve, or a follow-up the staff actually scheduled.
 * A follow-up that is now due is attention even when the last message was
 * outbound. A follow-up still in the future is not.
 * A missed call with no return and no handled mark is attention.
 * A missed call that was returned, or explicitly marked handled, is not.
 * Shop history is not attention: an estimate, review request, or invoice
 * email, or a customer opening the estimate portal, with no promised follow-up.
 */
beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-10-10 15:00:00'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('shop history without a promised follow-up is not needs attention', function () {
    $advisor = advisorAttentionAdvisor();
    $recorder = app(ConversationRecorder::class);

    foreach ([
        'Estimate emailed to customer with portal review link.',
        'Review request emailed to customer.',
        'Final invoice emailed to customer.',
    ] as $index => $body) {
        $recorder->recordRepairOrderEmail(
            advisorAttentionRepairOrder('7195551'.str_pad((string) $index, 3, '0', STR_PAD_LEFT)),
            $advisor,
            "history{$index}@example.test",
            $body,
        );
    }

    expect(advisorAttentionCounts($advisor))->toBe(['inbox' => 0, 'badge' => 0]);
});

test('a portal estimate view alone is not needs attention', function () {
    $advisor = advisorAttentionAdvisor();
    $repairOrder = advisorAttentionRepairOrder('7195551400');

    app(ConversationRecorder::class)->recordPortalEstimateView(
        $repairOrder,
        'Customer opened the estimate portal link.',
    );

    expect(advisorAttentionCounts($advisor))->toBe(['inbox' => 0, 'badge' => 0]);
});

test('an unanswered customer text is needs attention on the inbox and the badge', function () {
    $advisor = advisorAttentionAdvisor();

    app(ConversationRecorder::class)->recordInboundSms(
        normalizedPhone: '7195551500',
        body: 'Is the truck ready?',
        providerMessageSid: 'SM-attention-unanswered',
    );

    expect(advisorAttentionCounts($advisor))->toBe(['inbox' => 1, 'badge' => 1]);
});

test('a staff reply does not clear a customer question without resolve or a scheduled follow-up', function () {
    $advisor = advisorAttentionAdvisor();
    $recorder = app(ConversationRecorder::class);
    $message = $recorder->recordInboundSms(
        normalizedPhone: '7195551600',
        body: 'Can you check the noise?',
        providerMessageSid: 'SM-attention-question',
    );

    $recorder->recordOutboundSmsToConversation(
        $message->conversation,
        $advisor,
        'I will look and get back to you.',
        'SM-attention-reply',
    );

    expect(advisorAttentionCounts($advisor))->toBe(['inbox' => 1, 'badge' => 1]);
});

test('a promised follow-up that is now due is needs attention even after an outbound message', function () {
    $advisor = advisorAttentionAdvisor();
    $repairOrder = advisorAttentionRepairOrder('7195551700');
    $message = app(ConversationRecorder::class)->recordRepairOrderEmail(
        $repairOrder,
        $advisor,
        'followup-due@example.test',
        'Estimate emailed to customer with portal review link.',
    );

    app(ConversationWork::class)->followUp(
        $message->conversation,
        $advisor,
        now()->subHour(),
    );

    expect(advisorAttentionCounts($advisor))->toBe(['inbox' => 1, 'badge' => 1]);
});

test('a promised follow-up that is still in the future is not needs attention', function () {
    $advisor = advisorAttentionAdvisor();
    $repairOrder = advisorAttentionRepairOrder('7195551800');
    $message = app(ConversationRecorder::class)->recordRepairOrderEmail(
        $repairOrder,
        $advisor,
        'followup-later@example.test',
        'Estimate emailed to customer with portal review link.',
    );

    app(ConversationWork::class)->followUp(
        $message->conversation,
        $advisor,
        now()->addDay(),
    );

    expect(advisorAttentionCounts($advisor))->toBe(['inbox' => 0, 'badge' => 0]);
});

test('an unresolved missed call is needs attention on the inbox and the badge', function () {
    $advisor = advisorAttentionAdvisor();
    advisorAttentionMissedCall('CA-attention-missed', '7195551900');

    expect(advisorAttentionCounts($advisor))->toBe(['inbox' => 1, 'badge' => 1]);
});

test('a missed call that was later answered is not needs attention', function () {
    $advisor = advisorAttentionAdvisor();
    advisorAttentionMissedCall('CA-attention-returned-missed', '7195552000', now()->subMinutes(20));

    CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => 'CA-attention-returned-answered',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195552000',
        'to_number' => '+17195550100',
        'normalized_from' => '7195552000',
        'status' => CallSessionStatus::Answered,
        'hosted_outcome' => HostedCallOutcome::Answered,
        'started_at' => now()->subMinutes(5),
        'answered_at' => now()->subMinutes(5),
        'ended_at' => now()->subMinutes(2),
    ]);

    expect(advisorAttentionCounts($advisor))->toBe(['inbox' => 0, 'badge' => 0]);
});

test('an explicit resolve removes the conversation from needs attention', function () {
    $advisor = advisorAttentionAdvisor();
    $message = app(ConversationRecorder::class)->recordInboundSms(
        normalizedPhone: '7195552200',
        body: 'Can you call me back?',
        providerMessageSid: 'SM-attention-resolve',
    );

    app(ConversationWork::class)->resolve($message->conversation, $advisor);

    expect(advisorAttentionCounts($advisor))->toBe(['inbox' => 0, 'badge' => 0]);
});

test('a later customer text makes a resolved conversation need attention again', function () {
    $advisor = advisorAttentionAdvisor();
    $recorder = app(ConversationRecorder::class);
    $message = $recorder->recordInboundSms(
        normalizedPhone: '7195552300',
        body: 'Is the truck ready?',
        providerMessageSid: 'SM-attention-reopen-first',
    );

    app(ConversationWork::class)->resolve($message->conversation, $advisor);

    $recorder->recordInboundSms(
        normalizedPhone: '7195552300',
        body: 'Any update?',
        providerMessageSid: 'SM-attention-reopen-second',
    );

    expect(advisorAttentionCounts($advisor))->toBe(['inbox' => 1, 'badge' => 1]);
});

test('a missed call marked handled is not needs attention', function () {
    $advisor = advisorAttentionAdvisor();
    $call = advisorAttentionMissedCall('CA-attention-handled', '7195552100');

    app(CallSessionQueue::class)->markCallerHandled($call);

    expect(advisorAttentionCounts($advisor))->toBe(['inbox' => 0, 'badge' => 0]);
});

function advisorAttentionAdvisor(): User
{
    return User::factory()->create()->assignRole(ArkRole::Advisor->value);
}

/**
 * @return array{inbox: int, badge: int}
 */
function advisorAttentionCounts(User $advisor): array
{
    return [
        'inbox' => app(ConversationWork::class)->counts()['needs'],
        'badge' => app(CommunicationsNavPressure::class)->resolve($advisor)['attention_count'],
    ];
}

function advisorAttentionRepairOrder(string $phone): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Attention',
        'last_name' => 'Customer',
        'phone' => $phone,
        'email' => $phone.'@example.test',
    ]);

    $vehicle = \App\Ark\Operations\Vehicles\Vehicle::query()->create([
        'customer_id' => $customer->id,
        'year' => 2016,
        'make' => 'Ram',
        'model' => '2500',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => \App\Ark\Operations\RepairOrders\RepairOrderStatus::WaitingApproval,
        'concern_summary' => 'Attention acceptance.',
    ]);
}

function advisorAttentionMissedCall(string $sid, string $phone, ?Carbon $startedAt = null): CallSession
{
    return CallSession::query()->create([
        'provider' => TelephonyProviderType::Twilio,
        'provider_call_sid' => $sid,
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+1'.$phone,
        'to_number' => '+17195550100',
        'normalized_from' => $phone,
        'status' => CallSessionStatus::Missed,
        'hosted_outcome' => HostedCallOutcome::Missed,
        'started_at' => $startedAt ?? now()->subMinutes(10),
        'ended_at' => $startedAt ?? now()->subMinutes(10),
    ]);
}
