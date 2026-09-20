<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\InspectionCaptureLinks;
use App\Ark\Operations\Messaging\PhoneSmsCapability;
use App\Ark\Operations\PhoneNumber;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Mail\InspectionWalkLinkStaffMail;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
        
    ShopSettings::current()->update([
        'telephony_inbound_number' => '7195559999',
        'shop_name' => 'Demo Auto Repair',
    ]);
});

function seedWalkLinkSmsCapable(string $phone): void
{
    $normalized = PhoneNumber::normalize($phone) ?? preg_replace('/\D+/', '', $phone);

    PhoneSmsCapability::query()->updateOrCreate(
        ['normalized_phone' => $normalized],
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

function walkLinkRepairOrder(User $technician): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Walk',
        'last_name' => 'Link',
        'phone' => '7195554401',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'WLK1',
        'year' => 2016,
        'make' => 'Honda',
        'model' => 'CR-V',
        'vin' => '5J6RM4H37GL012345',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::InProgress,
        'concern_summary' => 'Walk link handoff.',
        'assigned_technician_id' => $technician->id,
    ]);

    RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Inspection walk',
        'disposition' => 'draft',
        'position' => 0,
    ]);

    return $repairOrder->fresh(['vehicle', 'customer']);
}

test('walk link sms sends through twilio to staff phone', function (): void {
    seedWalkLinkSmsCapable('7195551002');
    bindFakeOutboundSms('SMwalk01');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create([
        'name' => 'Terry Tech',
        'phone' => '7195551002',
        'email' => 'terry@example.com',
    ])->assignRole(ArkRole::Technician->value);

    $repairOrder = walkLinkRepairOrder($technician);
    $walkUrl = InspectionCaptureLinks::walkUrl($repairOrder);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.inspection.walk-link.send', $repairOrder), [
            'user_id' => $technician->id,
            'delivery' => 'sms',
        ])
        ->assertOk()
        ->assertJsonPath('sms_sent', true)
        ->assertJsonPath('email_sent', false);
});

test('walk link email sends through laravel mail to staff', function (): void {
    Mail::fake();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create([
        'name' => 'Terry Tech',
        'phone' => '7195551002',
        'email' => 'terry@example.com',
    ])->assignRole(ArkRole::Technician->value);

    $repairOrder = walkLinkRepairOrder($technician);
    $walkUrl = InspectionCaptureLinks::walkUrl($repairOrder);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.inspection.walk-link.send', $repairOrder), [
            'user_id' => $technician->id,
            'delivery' => 'email',
        ])
        ->assertOk()
        ->assertJsonPath('email_sent', true)
        ->assertJsonPath('sms_sent', false);

    Mail::assertSent(InspectionWalkLinkStaffMail::class, function (InspectionWalkLinkStaffMail $mail) use ($technician, $walkUrl): bool {
        return $mail->hasTo($technician->email)
            && $mail->walkUrl === $walkUrl
            && $mail->recipientName === 'Terry Tech';
    });
});

test('walk link sms fails when staff has no phone', function (): void {
    bindFakeOutboundSms();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create([
        'name' => 'No Phone Tech',
        'phone' => null,
        'email' => 'nophone@example.com',
    ])->assignRole(ArkRole::Technician->value);

    $repairOrder = walkLinkRepairOrder($technician);

    $this->actingAs($advisor)
        ->postJson(route('operations.repair-orders.inspection.walk-link.send', $repairOrder), [
            'user_id' => $technician->id,
            'delivery' => 'sms',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No Phone Tech has no phone on file.');
});
