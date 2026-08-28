<?php

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Work\AdvisorFollowUp;
use App\Ark\Operations\Work\AdvisorTask;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('work surface shows follow ups and tasks grouped by due bucket', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Maricruz',
        lastName: 'Lopez',
        status: \App\Ark\Operations\RepairOrders\RepairOrderStatus::WaitingApproval,
        lineCents: 900_600,
    );

    AdvisorFollowUp::query()->create([
        'created_by_user_id' => $advisor->id,
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'notes' => 'Call Thursday about brakes',
        'due_at' => Carbon::parse('2026-06-09 15:00:00'),
    ]);

    AdvisorTask::query()->create([
        'created_by_user_id' => $advisor->id,
        'notes' => 'Check warranty claim status',
        'due_at' => Carbon::parse('2026-06-10 14:00:00'),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.work.queue', 'follow-ups'))
        ->assertOk()
        ->assertSee('Follow-Ups')
        ->assertSee('Call Thursday about brakes')
        ->assertSee('Overdue')
        ->assertSee('overdue', false);

    $this->actingAs($advisor)
        ->get(route('operations.work.queue', 'tasks'))
        ->assertOk()
        ->assertSee('Tasks')
        ->assertSee('Check warranty claim status')
        ->assertSee('Shop task', false);

    $this->actingAs($advisor)
        ->get(route('operations.work.queue', 'decisions'))
        ->assertOk()
        ->assertSee('Customer Decisions')
        ->assertSee('Maricruz Lopez')
        ->assertSee('$9,006')
        ->assertDontSee("Today's Appointments", false);

    Carbon::setTestNow();
});

test('advisor can create and complete a follow up from work surface', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Ben',
        lastName: 'Trainee',
        status: \App\Ark\Operations\RepairOrders\RepairOrderStatus::WaitingApproval,
        lineCents: 100_000,
    );

    $this->actingAs($advisor)
        ->post(route('operations.work.follow-ups.store'), [
            'notes' => 'Waiting on husband approval',
            'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'repair_order_shop_number' => $repairOrder->repair_order_id,
        ])
        ->assertRedirect(route('operations.index'));

    $followUp = AdvisorFollowUp::query()->sole();

    expect($followUp->notes)->toBe('Waiting on husband approval')
        ->and($followUp->repair_order_id)->toBe($repairOrder->id);

    $this->patch(route('operations.work.follow-ups.complete', $followUp))
        ->assertRedirect(route('operations.index'));

    expect($followUp->fresh()->completed_at)->not->toBeNull();
});

test('advisor can create follow up and task via json from customer decisions quick add', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Ben',
        lastName: 'Trainee',
        status: \App\Ark\Operations\RepairOrders\RepairOrderStatus::WaitingApproval,
        lineCents: 100_000,
    );

    $this->actingAs($advisor)
        ->postJson(route('operations.work.follow-ups.store'), [
            'notes' => 'Follow up: Ben Trainee · RO #'.$repairOrder->repair_order_id,
            'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'repair_order_shop_number' => $repairOrder->repair_order_id,
            'customer_id' => $repairOrder->customer_id,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Follow-up added to Work.')
        ->assertJsonPath('kind', 'follow-up');

    $this->actingAs($advisor)
        ->postJson(route('operations.work.tasks.store'), [
            'notes' => 'Task: Ben Trainee · RO #'.$repairOrder->repair_order_id,
            'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'repair_order_shop_number' => $repairOrder->repair_order_id,
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Task added to Work.')
        ->assertJsonPath('kind', 'task');

    expect(AdvisorFollowUp::query()->count())->toBe(1)
        ->and(AdvisorTask::query()->count())->toBe(1);
});

test('advisor can create standalone follow up and task without repair order', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();

    $this->actingAs($advisor)
        ->post(route('operations.work.follow-ups.store'), [
            'notes' => 'Call customer Thursday — waiting on husband approval',
            'due_at' => Carbon::parse('2026-06-12 10:00:00')->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect(route('operations.index'));

    $this->actingAs($advisor)
        ->post(route('operations.work.tasks.store'), [
            'notes' => 'Call machine shop about head gasket quote',
            'due_at' => Carbon::parse('2026-06-10 14:00:00')->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect(route('operations.index'));

    $this->actingAs($advisor)
        ->get(route('operations.work.queue', 'follow-ups'))
        ->assertOk()
        ->assertSee('Call customer Thursday — waiting on husband approval')
        ->assertSee('Due Fri Jun 12', false);

    $this->actingAs($advisor)
        ->get(route('operations.work.queue', 'tasks'))
        ->assertOk()
        ->assertSee('Call machine shop about head gasket quote')
        ->assertSee('Due today', false);

    Carbon::setTestNow();
});

test('work surface customer decision rows expose follow up and task quick add on full queue', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    decisionPressureRepairOrder(
        firstName: 'Ben',
        lastName: 'Trainee',
        status: \App\Ark\Operations\RepairOrders\RepairOrderStatus::WaitingApproval,
        lineCents: 100_000,
    );

    $this->actingAs($advisor)
        ->get(route('operations.work.queue', 'decisions'))
        ->assertOk()
        ->assertSee('ops-work-item-quick-add-form', false)
        ->assertSee('Save follow-up', false)
        ->assertSee('Save task', false)
        ->assertSee('data-work-item-quick-add-cancel', false)
        ->assertSee('Save schedule', false)
        ->assertSee('Schedule · Ben Trainee', false)
        ->assertSee('Follow-up · Ben Trainee', false);
});

test('follow ups and tasks are visible shop wide with assigned advisor label', function () {
    $ben = actingAsLearnCurrentAdvisor();
    $molly = actingAsLearnCurrentStaff(ArkRole::Advisor);

    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Maricruz',
        lastName: 'Lopez',
        status: \App\Ark\Operations\RepairOrders\RepairOrderStatus::WaitingApproval,
        lineCents: 900_600,
    );

    AdvisorFollowUp::query()->create([
        'created_by_user_id' => $ben->id,
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'notes' => 'Call Maricruz Friday',
        'due_at' => now()->addDay(),
    ]);

    $this->actingAs($molly)
        ->get(route('operations.work.queue', 'follow-ups'))
        ->assertOk()
        ->assertSee('Call Maricruz Friday')
        ->assertSee('Assigned to '.$ben->name, false);
});

test('work lists sort current advisor items before teammates in each bucket', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
    $ben = actingAsLearnCurrentAdvisor();
    $molly = actingAsLearnCurrentStaff(ArkRole::Advisor);

    AdvisorFollowUp::query()->create([
        'created_by_user_id' => $ben->id,
        'notes' => 'Ben teammate follow-up',
        'due_at' => Carbon::parse('2026-06-10 10:00:00'),
    ]);

    AdvisorFollowUp::query()->create([
        'created_by_user_id' => $molly->id,
        'notes' => 'Molly own follow-up',
        'due_at' => Carbon::parse('2026-06-10 11:00:00'),
    ]);

    AdvisorTask::query()->create([
        'created_by_user_id' => $ben->id,
        'notes' => 'Ben teammate task',
        'due_at' => Carbon::parse('2026-06-10 12:00:00'),
    ]);

    AdvisorTask::query()->create([
        'created_by_user_id' => $molly->id,
        'notes' => 'Molly own task',
        'due_at' => Carbon::parse('2026-06-10 13:00:00'),
    ]);

    $html = $this->actingAs($molly)
        ->get(route('operations.work.queue', 'follow-ups'))
        ->assertOk()
        ->assertSee('Ben teammate follow-up')
        ->assertSee('Molly own follow-up')
        ->getContent();

    expect(mb_strpos($html, 'Molly own follow-up'))->toBeLessThan(mb_strpos($html, 'Ben teammate follow-up'));

    $taskHtml = $this->actingAs($molly)
        ->get(route('operations.work.queue', 'tasks'))
        ->assertOk()
        ->assertSee('Ben teammate task')
        ->assertSee('Molly own task')
        ->getContent();

    expect(mb_strpos($taskHtml, 'Molly own task'))->toBeLessThan(mb_strpos($taskHtml, 'Ben teammate task'));

    Carbon::setTestNow();
});

test('any advisor can complete a teammates follow up', function () {
    $ben = actingAsLearnCurrentAdvisor();
    $molly = actingAsLearnCurrentStaff(ArkRole::Advisor);

    $followUp = AdvisorFollowUp::query()->create([
        'created_by_user_id' => $ben->id,
        'notes' => 'Ben follow-up for Molly to close',
        'due_at' => now()->addDay(),
    ]);

    $this->actingAs($molly)
        ->patch(route('operations.work.follow-ups.complete', $followUp))
        ->assertRedirect(route('operations.index'));

    expect($followUp->fresh()->completed_at)->not->toBeNull();
});

test('advisor can complete follow up from customer hub without leaving page', function () {
    $ben = actingAsLearnCurrentAdvisor();
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Maricruz',
        lastName: 'Lopez',
        status: \App\Ark\Operations\RepairOrders\RepairOrderStatus::WaitingApproval,
        lineCents: 900_600,
    );

    $followUp = AdvisorFollowUp::query()->create([
        'created_by_user_id' => $ben->id,
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'notes' => 'Call Maricruz Friday',
        'due_at' => now()->addDay(),
    ]);

    $customerUrl = route('operations.customers.show', $repairOrder->customer_id);

    $this->actingAs($ben)
        ->from($customerUrl)
        ->patch(route('operations.work.follow-ups.complete', $followUp), [
            'redirect' => $customerUrl,
        ])
        ->assertRedirect($customerUrl);

    expect($followUp->fresh()->completed_at)->not->toBeNull();
});

test('advisor can complete follow up from repair order workspace', function () {
    $ben = actingAsLearnCurrentAdvisor();
    $repairOrder = decisionPressureRepairOrder(
        firstName: 'Maricruz',
        lastName: 'Lopez',
        status: \App\Ark\Operations\RepairOrders\RepairOrderStatus::WaitingApproval,
        lineCents: 900_600,
    );

    $followUp = AdvisorFollowUp::query()->create([
        'created_by_user_id' => $ben->id,
        'repair_order_id' => $repairOrder->id,
        'customer_id' => $repairOrder->customer_id,
        'notes' => 'Call Maricruz Friday',
        'due_at' => now()->addDay(),
    ]);

    $repairOrderUrl = route('operations.repair-orders.show', $repairOrder);

    $this->actingAs($ben)
        ->from($repairOrderUrl)
        ->patch(route('operations.work.follow-ups.complete', $followUp), [
            'redirect' => $repairOrderUrl,
        ])
        ->assertRedirect($repairOrderUrl);

    expect($followUp->fresh()->completed_at)->not->toBeNull();
});

test('appointment routes stay hidden until appointments surface is enabled', function () {
    ShopSettings::current()->update(['appointments_enabled' => false]);

    $this->actingAs(actingAsLearnCurrentAdvisor())
        ->get(route('operations.appointments.index'))
        ->assertNotFound();
});

test('work surface bands link to full page queues', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
    $advisor = actingAsLearnCurrentAdvisor();

    AdvisorTask::query()->create([
        'created_by_user_id' => $advisor->id,
        'notes' => 'Order shop towels',
        'due_at' => Carbon::parse('2026-06-10 14:00:00'),
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.work.queue', 'tasks'))
        ->assertOk()
        ->assertSee('Order shop towels')
        ->assertSee('Add task', false);

    $this->actingAs($advisor)
        ->get(route('operations.work.queue', 'decisions'))
        ->assertOk()
        ->assertSee('Customer Decision Needed')
        ->assertSee('Estimate Ready · Not Sent');

    Carbon::setTestNow();
});
