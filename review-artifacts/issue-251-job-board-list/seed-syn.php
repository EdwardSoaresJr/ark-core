<?php

use App\Ark\Operations\Appointments\Appointment;
use App\Ark\Operations\Appointments\AppointmentStatus;
use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Inspections\Inspection;
use App\Ark\Operations\Telephony\CallSession;
use App\Ark\Operations\Telephony\CallSessionDirection;
use App\Ark\Operations\Telephony\CallSessionStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\JobBoardAcceptanceScenario;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

app(ArkAuthorizationSeeder::class)->run();
app(RepairOrderStatusCatalogSeeder::class)->run();

$email = 'jobboard-review-251@example.test';
$user = User::query()->where('email', $email)->first();
if (! $user) {
    $user = User::factory()->create([
        'name' => 'Job Board Reviewer',
        'email' => $email,
        'password' => Hash::make('Review251!'),
    ]);
} else {
    $user->forceFill(['password' => Hash::make('Review251!')])->save();
}
$user->syncRoles([ArkRole::Advisor->value, ArkRole::Admin->value]);

// Clear prior SYN activity (layout purge cannot touch communications namespaces).
$priorCustomerIds = \App\Ark\Operations\Customers\Customer::query()
    ->where('email', 'like', 'syn.%.251@example.test')
    ->pluck('id');
$priorRoIds = \App\Ark\Operations\RepairOrders\RepairOrder::query()
    ->whereIn('customer_id', $priorCustomerIds)
    ->pluck('id');
if ($priorRoIds->isNotEmpty()) {
    DB::table('communication_events')->whereIn('repair_order_id', $priorRoIds)->delete();
    CallSession::query()->whereIn('repair_order_id', $priorRoIds)->delete();
    Inspection::query()->whereIn('repair_order_id', $priorRoIds)->delete();
    Appointment::query()->whereIn('repair_order_id', $priorRoIds)->delete();
}

$rows = JobBoardAcceptanceScenario::seed($user);
$byLast = $rows->keyBy(fn ($ro) => (string) $ro->customer?->last_name);

$event = function ($ro, $type, $channel, $direction, $at) {
    CommunicationEvent::query()->create([
        'repair_order_id' => $ro->id,
        'event_type' => $type,
        'channel' => $channel,
        'direction' => $direction,
        'summary' => 'SYN-JOBBOARD-251 '.$type->value,
        'occurred_at' => $at,
    ]);
};

$comms = $byLast->get('Comms Pair');
$wip = $byLast->get('Wip Bay');
$missed = $byLast->get('Parts Hold');
$full = $byLast->get('Full Strip');
$ready = $byLast->get('Ready Pickup');

if ($comms) {
    $event($comms, OperationalCommunicationType::SmsDelivered, OperationalCommunicationChannel::Sms, OperationalCommunicationDirection::Outbound, now()->subHours(3));
    $event($comms, OperationalCommunicationType::InvoiceSent, OperationalCommunicationChannel::Email, OperationalCommunicationDirection::Outbound, now()->subHours(2));
}

if ($wip) {
    $event($wip, OperationalCommunicationType::EstimateViewed, OperationalCommunicationChannel::Website, OperationalCommunicationDirection::Inbound, now()->subHour());
    Inspection::query()->create([
        'repair_order_id' => $wip->id,
        'started_at' => now()->subHours(4),
        'completed_at' => now()->subHours(3),
    ]);
}

if ($missed) {
    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAsyn251missedseed',
        'direction' => CallSessionDirection::Inbound,
        'from_number' => '+17195552002',
        'to_number' => '+17195559999',
        'normalized_from' => '7195552002',
        'status' => CallSessionStatus::Missed,
        'repair_order_id' => $missed->id,
        'started_at' => now()->subHours(5),
    ]);
}

if ($full) {
    $event($full, OperationalCommunicationType::EstimateViewed, OperationalCommunicationChannel::Website, OperationalCommunicationDirection::Inbound, now()->subHours(6));
    $event($full, OperationalCommunicationType::CustomerReply, OperationalCommunicationChannel::Sms, OperationalCommunicationDirection::Inbound, now()->subMinutes(40));
    $event($full, OperationalCommunicationType::MessageRead, OperationalCommunicationChannel::Email, OperationalCommunicationDirection::Inbound, now()->subHours(2));
    CallSession::query()->create([
        'provider' => 'twilio',
        'provider_call_sid' => 'CAsyn251workedseed',
        'direction' => CallSessionDirection::Outbound,
        'from_number' => '+17195559999',
        'to_number' => '+17195552007',
        'normalized_from' => '7195559999',
        'status' => CallSessionStatus::Completed,
        'repair_order_id' => $full->id,
        'started_at' => now()->subHours(8),
        'worked_at' => now()->subHours(7),
    ]);
    Inspection::query()->create([
        'repair_order_id' => $full->id,
        'started_at' => now()->subDay(),
        'completed_at' => now()->subDay()->addHours(2),
    ]);
    Appointment::query()->create([
        'customer_id' => $full->customer_id,
        'vehicle_id' => $full->vehicle_id,
        'repair_order_id' => $full->id,
        'created_by_user_id' => $user->id,
        'advisor_user_id' => $user->id,
        'starts_at' => now()->addDay()->setTime(10, 0),
        'ends_at' => now()->addDay()->setTime(11, 0),
        'concern' => 'SYN follow-up',
        'status' => AppointmentStatus::Scheduled,
    ]);
}

if ($ready) {
    $event($ready, OperationalCommunicationType::SmsDeliveryFailed, OperationalCommunicationChannel::Sms, OperationalCommunicationDirection::Outbound, now()->subMinutes(25));
    $event($ready, OperationalCommunicationType::EstimateSent, OperationalCommunicationChannel::Sms, OperationalCommunicationDirection::Outbound, now()->subDay());
}

echo 'seeded='.$rows->count().PHP_EOL;
foreach ($rows as $ro) {
    echo $ro->repair_order_id.' '.$ro->status->value.' '.(string) $ro->customer?->name
        .' tech='.(string) $ro->assignedTechnician?->name.PHP_EOL;
}
