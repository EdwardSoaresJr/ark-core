<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\RepairOrders\RepairActionOwnerType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderLostReason;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalog;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusCatalogDefaults;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusColor;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusDefinition;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusTransition;
use App\Ark\Operations\RepairOrders\Status\RepairOrderStatusTransitionRole;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;

beforeEach(function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
});

test('non-canonical status transitions are deactivated so menus stay operational', function () {
    $catalog = app(RepairOrderStatusCatalog::class);

    RepairOrderStatusTransition::query()->updateOrCreate(
        [
            'from_status_slug' => RepairOrderStatus::WaitingApproval->value,
            'to_status_slug' => RepairOrderStatus::Invoiced->value,
        ],
        ['active' => true],
    );

    $catalog->forgetCache();

    expect($catalog->allowedTargetSlugs(RepairOrderStatus::WaitingApproval->value))
        ->toContain(RepairOrderStatus::Invoiced->value);

    RepairOrderStatusCatalogDefaults::deactivateNonCanonicalTransitions($catalog);

    expect($catalog->allowedTargetSlugs(RepairOrderStatus::WaitingApproval->value))
        ->toBe(['estimate', 'approved'])
        ->and(
            RepairOrderStatusTransition::query()
                ->where('from_status_slug', RepairOrderStatus::WaitingApproval->value)
                ->where('to_status_slug', RepairOrderStatus::Invoiced->value)
                ->value('active')
        )->toBeFalsy();
});

test('advisor workboard exposes consolidated shop pressure lanes from catalog', function () {
    $catalog = app(RepairOrderStatusCatalog::class);

    expect($catalog->advisorWorkboardLanes())->toHaveCount(5)
        ->and(collect($catalog->advisorWorkboardLanes())->pluck('label')->all())->toBe([
            'Estimates',
            'Waiting Approval',
            'Waiting Parts',
            'Work in Progress',
            'Completed',
        ]);
});

test('intake index redirects to job board; check-in remains on create', function () {
    $advisor = actingAsLearnCurrentAdvisor();

    $draft = statusCatalogRepairOrder(RepairOrderStatus::Draft);
    $draft->update([
        'concern_summary' => 'INTAKE-QUEUE-DRAFT-CARD',
        'drop_off' => true,
    ]);

    $this->actingAs($advisor)
        ->get(route('operations.intake.index'))
        ->assertRedirect(route('operations.index'));

    $this->actingAs($advisor)
        ->followingRedirects()
        ->get(route('operations.intake.create', ['ws' => 'testintake01']))
        ->assertOk()
        ->assertSee('Check In', false);
});

test('operations workboard includes intake statuses in needs diagnosis queue', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $draft = statusCatalogRepairOrder(RepairOrderStatus::Draft);
    $draft->customer?->update(['first_name' => 'Intake', 'last_name' => 'Draftcard']);
    statusCatalogLine($draft);

    $waiting = statusCatalogRepairOrder(RepairOrderStatus::WaitingApproval);
    $waiting->customer?->update(['first_name' => 'Lifecycle', 'last_name' => 'Waitingcard']);
    statusCatalogLine($waiting);

    $this->get(route('operations.workboard', ['queue' => 'needs_diagnosis']))
        ->assertRedirect(route('operations.index', ['queue' => 'needs_diagnosis']));

    $this->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Intake Draftcard', false)
        ->assertSee('Lifecycle Waitingcard', false)
        ->assertSee('Estimates', false)
        ->assertSee('Waiting Approval', false);
});

test('operations workboard renders queue nav labels for advisor lanes', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $seed = statusCatalogRepairOrder(RepairOrderStatus::WaitingApproval);
    $seed->customer?->update(['first_name' => 'Swimlane', 'last_name' => 'Structure']);
    statusCatalogLine($seed);

    $this->get(route('operations.workboard'))
        ->assertRedirect(route('operations.index'));

    $this->get(route('operations.index'))
        ->assertOk()
        ->assertSee('Estimates', false)
        ->assertSee('Waiting Approval', false)
        ->assertSee('Waiting Parts', false)
        ->assertSee('Work in Progress', false)
        ->assertSee('Completed', false)
        ->assertSee('Swimlane Structure', false);
});

test('lnp status catalog seeds transitions with role matrix', function () {
    $catalog = app(RepairOrderStatusCatalog::class);

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);

    expect($catalog->isBooted())->toBeTrue()
        ->and($catalog->canTransition(
            RepairOrderStatus::Estimate,
            RepairOrderStatus::WaitingApproval,
            actor: $advisor,
        ))->toBeTrue()
        ->and($catalog->canTransition(
            RepairOrderStatus::Estimate,
            RepairOrderStatus::WaitingApproval,
            actor: $technician,
        ))->toBeFalse()
        ->and($catalog->canTransition(
            RepairOrderStatus::Approved,
            RepairOrderStatus::InProgress,
            actor: $technician,
        ))->toBeTrue();
});

test('status catalog settings expose a full transition matrix for every status', function () {
    $catalog = app(RepairOrderStatusCatalog::class);
    $formData = $catalog->settingsFormData();
    $statusCount = count($formData);

    expect($statusCount)->toBeGreaterThan(1);

    foreach ($formData as $status) {
        expect($status['transitions'])->toHaveCount($statusCount - 1)
            ->and(collect($status['transitions'])->pluck('to')->all())->not->toContain($status['slug']);
    }

    $draft = collect($formData)->firstWhere('slug', RepairOrderStatus::Draft->value);
    $draftToEstimate = collect($draft['transitions'])->firstWhere('to', RepairOrderStatus::Estimate->value);

    expect(collect($draft['transitions'])->pluck('to_name')->all())->toContain('Building Estimate')
        ->and(collect($draft['transitions'])->pluck('to_name')->all())->toContain('Waiting Approval')
        ->and($draftToEstimate['roles'])->toContain(ArkRole::Admin->value)
        ->and($draftToEstimate['roles'])->toContain(ArkRole::Advisor->value)
        ->and($draftToEstimate['roles'])->not->toContain(ArkRole::Technician->value);
});

test('default transition roles include admin and advisor with technician only on bay moves', function () {
    expect(RepairOrderStatusCatalogDefaults::defaultRolesForTransition('draft', 'estimate'))
        ->toBe([ArkRole::Admin->value, ArkRole::Advisor->value])
        ->and(RepairOrderStatusCatalogDefaults::defaultRolesForTransition('approved', 'in_progress'))
        ->toContain(ArkRole::Technician->value)
        ->and(RepairOrderStatusCatalogDefaults::defaultRolesForTransition('draft', 'waiting_approval'))
        ->toBe([ArkRole::Admin->value]);
});

test('admin can enable a previously unset transition from the settings matrix', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $this->actingAs($admin);

    RepairOrderStatusTransition::query()
        ->where('from_status_slug', RepairOrderStatus::Draft->value)
        ->where('to_status_slug', RepairOrderStatus::InProgress->value)
        ->delete();

    app(RepairOrderStatusCatalog::class)->forgetCache();

    $this->from(route('operations.settings.shop.edit', ['section' => 'workflow', 'workflow-tab' => 'statuses']))
        ->patch(route('operations.settings.shop.status-catalog.update'), [
            'transitions' => [
                'new:draft:in_progress' => [
                    'roles' => [ArkRole::Admin->value, ArkRole::Advisor->value],
                ],
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'workflow',
            'workflow-tab' => 'statuses',
        ]));

    app(RepairOrderStatusCatalog::class)->forgetCache();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    expect(app(RepairOrderStatusCatalog::class)->canTransition(
        RepairOrderStatus::Draft,
        RepairOrderStatus::InProgress,
        actor: $advisor,
    ))->toBeTrue();
});

test('advisors can revert ready for pickup back to in progress', function () {
    $catalog = app(RepairOrderStatusCatalog::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    expect($catalog->canTransition(
        RepairOrderStatus::ReadyPickup,
        RepairOrderStatus::InProgress,
        actor: $advisor,
    ))->toBeTrue();
});

test('quality check is a built in work in progress status with advisor transitions', function () {
    $catalog = app(RepairOrderStatusCatalog::class);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $definition = RepairOrderStatusDefinition::query()
        ->where('slug', RepairOrderStatus::QualityCheck->value)
        ->firstOrFail();

    expect($definition->dashboard_group_name)->toBe('Work in progress')
        ->and($definition->show_on_advisor_board)->toBeTrue()
        ->and($definition->show_on_technician_board)->toBeTrue()
        ->and($catalog->canTransition(
            RepairOrderStatus::InProgress,
            RepairOrderStatus::QualityCheck,
            actor: $advisor,
        ))->toBeTrue()
        ->and($catalog->canTransition(
            RepairOrderStatus::QualityCheck,
            RepairOrderStatus::InProgress,
            actor: $advisor,
        ))->toBeTrue();
});

test('technicians can advance approved work through the lnp matrix', function () {
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $this->actingAs($technician);

    $repairOrder = statusCatalogRepairOrder(RepairOrderStatus::Approved);
    statusCatalogLine($repairOrder);
    $repairOrder->forceFill(['assigned_technician_id' => $technician->id])->save();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::InProgress->value,
    ])->assertRedirect();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::InProgress))->toBeTrue();
});

test('advisors can close lost deals without paid closeout blockers', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $repairOrder = statusCatalogRepairOrder(RepairOrderStatus::WaitingApproval);
    statusCatalogLine($repairOrder);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:lost',
        'lost_reason_key' => RepairOrderLostReason::PriceDeclined->value,
    ])->assertRedirect();

    $repairOrder = $repairOrder->fresh();

    expect($repairOrder->status->is(RepairOrderStatus::Closed))->toBeTrue()
        ->and($repairOrder->close_variant_key)->toBe('lost')
        ->and($repairOrder->closeCountsInSalesMetrics())->toBeFalse();
});

test('advisors can close lost building estimates without estimate lines', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $this->actingAs($advisor);

    $repairOrder = statusCatalogRepairOrder(RepairOrderStatus::Estimate);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:lost',
        'lost_reason_key' => RepairOrderLostReason::NoResponse->value,
    ])->assertRedirect();

    expect($repairOrder->fresh()->close_variant_key)->toBe('lost');
});

test('building estimate lifecycle select offers close lost without estimate lines', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $repairOrder = statusCatalogRepairOrder(RepairOrderStatus::Estimate);

    $this->actingAs($advisor)
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Closed - Lost', false);
});

test('technicians can close lost when status catalog grants closed transition', function () {
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $this->actingAs($technician);

    $transition = RepairOrderStatusTransition::query()
        ->where('from_status_slug', RepairOrderStatus::Estimate->value)
        ->where('to_status_slug', RepairOrderStatus::Closed->value)
        ->firstOrFail();

    RepairOrderStatusTransitionRole::query()
        ->where('transition_id', $transition->id)
        ->delete();

    RepairOrderStatusTransitionRole::query()->create([
        'transition_id' => $transition->id,
        'role' => ArkRole::Technician->value,
    ]);

    app(RepairOrderStatusCatalog::class)->forgetCache();

    $repairOrder = statusCatalogRepairOrder(RepairOrderStatus::Estimate);

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => 'closed:lost',
        'lost_reason_key' => RepairOrderLostReason::PriceDeclined->value,
    ])->assertRedirect();

    expect($repairOrder->fresh()->close_variant_key)->toBe('lost');
});

test('shop workflow settings expose the repair order status catalog', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $this->actingAs($admin);

    $this->get(route('operations.settings.shop.edit', ['section' => 'workflow', 'workflow-tab' => 'statuses']))
        ->assertOk()
        ->assertSee('RO statuses')
        ->assertSee('Job Board lanes')
        ->assertSee('Waiting Approval')
        ->assertSee('Paid')
        ->assertSee('Save status catalog')
        ->assertSee('Mileage required', false)
        ->assertSee('>Mileage in</option>', false)
        ->assertSee('>Mileage out</option>', false)
        ->assertSee('>Both</option>', false)
        ->assertSee('name="statuses[in_progress][mileage_requirement]"', false)
        ->assertSee('name="statuses[waiting_approval][color]"', false)
        ->assertSee('Waiting')
        ->assertSee('In motion')
        ->assertSee('#f97316', false);
});

test('admin can change status catalog colors and chips follow catalog', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $this->actingAs($admin);

    expect(RepairOrderStatus::WaitingApproval->indexTone())->toBe('approval');

    $this->from(route('operations.settings.shop.edit', ['section' => 'workflow', 'workflow-tab' => 'statuses']))
        ->patch(route('operations.settings.shop.status-catalog.update'), [
            'statuses' => [
                RepairOrderStatus::WaitingApproval->value => [
                    'name' => 'Waiting Approval',
                    'color' => RepairOrderStatusColor::SUCCESS,
                    'show_on_advisor_board' => '1',
                    'show_on_technician_board' => '0',
                ],
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'workflow',
            'workflow-tab' => 'statuses',
        ]));

    app(RepairOrderStatusCatalog::class)->forgetCache();

    expect(RepairOrderStatusDefinition::query()->where('slug', RepairOrderStatus::WaitingApproval->value)->value('color'))
        ->toBe(RepairOrderStatusColor::SUCCESS)
        ->and(app(RepairOrderStatusCatalog::class)->colorForSlug(RepairOrderStatus::WaitingApproval->value))
        ->toBe(RepairOrderStatusColor::SUCCESS)
        ->and(RepairOrderStatus::WaitingApproval->indexTone())
        ->toBe('ready');
});

test('status catalog filter options use seeded display names', function () {
    $catalog = app(RepairOrderStatusCatalog::class);

    $labels = collect($catalog->filterOptions())->pluck('label', 'value');

    expect($labels->get('draft'))->toBe('Draft')
        ->and($labels->get('waiting_approval'))->toBe('Waiting Approval')
        ->and($labels->get('ready_pickup'))->toBe('Ready for Pickup')
        ->and($labels->get('invoiced'))->toBe('Invoiced');
});

test('sync status catalog command refreshes defaults', function () {
    RepairOrderStatusDefinition::query()
        ->where('slug', RepairOrderStatus::Draft->value)
        ->update(['name' => 'Broken Draft Label']);

    $this->artisan('repair-orders:sync-status-catalog')->assertSuccessful();

    app(RepairOrderStatusCatalog::class)->forgetCache();

    expect(app(RepairOrderStatusCatalog::class)->labelForSlug(RepairOrderStatus::Draft->value))
        ->toBe('Draft');
});

test('sync status catalog --if-empty leaves an existing catalog alone', function () {
    RepairOrderStatusDefinition::query()
        ->where('slug', RepairOrderStatus::Draft->value)
        ->update(['name' => 'Shop Draft']);

    $this->artisan('repair-orders:sync-status-catalog', ['--if-empty' => true])->assertSuccessful();

    app(RepairOrderStatusCatalog::class)->forgetCache();

    expect(app(RepairOrderStatusCatalog::class)->labelForSlug(RepairOrderStatus::Draft->value))
        ->toBe('Shop Draft');
});

test('admin can rename statuses and disable transitions in settings', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $this->actingAs($admin);

    $transition = RepairOrderStatusTransition::query()
        ->where('from_status_slug', RepairOrderStatus::Estimate->value)
        ->where('to_status_slug', RepairOrderStatus::WaitingApproval->value)
        ->firstOrFail();

    $this->from(route('operations.settings.shop.edit', ['section' => 'workflow', 'workflow-tab' => 'statuses']))
        ->patch(route('operations.settings.shop.status-catalog.update'), [
            'statuses' => [
                RepairOrderStatus::WaitingApproval->value => [
                    'name' => 'Customer Review',
                    'show_on_advisor_board' => '1',
                    'show_on_technician_board' => '0',
                ],
            ],
            'transitions' => [
                $transition->id => [
                    'roles' => [],
                ],
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'workflow',
            'workflow-tab' => 'statuses',
        ]));

    app(RepairOrderStatusCatalog::class)->forgetCache();

    expect(app(RepairOrderStatusCatalog::class)->labelForSlug(RepairOrderStatus::WaitingApproval->value))
        ->toBe('Customer Review')
        ->and(app(RepairOrderStatusCatalog::class)->canTransition(
            RepairOrderStatus::Estimate,
            RepairOrderStatus::WaitingApproval,
            actor: User::factory()->create()->assignRole(ArkRole::Advisor->value),
        ))->toBeFalse();
});

test('technicians see a filtered workboard for assigned bay work', function () {
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);
    $this->actingAs($technician);

    $assigned = statusCatalogRepairOrder(RepairOrderStatus::InProgress);
    $assigned->update(['concern_summary' => 'TECH-ASSIGNED-BAY-WORK']);
    statusCatalogOwnedLine($assigned, $technician);

    $otherTech = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $foreignBay = statusCatalogRepairOrder(RepairOrderStatus::InProgress);
    $foreignBay->update(['concern_summary' => 'TECH-FOREIGN-BAY-WORK']);
    statusCatalogOwnedLine($foreignBay, $otherTech);

    $draft = statusCatalogRepairOrder(RepairOrderStatus::Draft);
    $draft->update(['concern_summary' => 'TECH-HIDDEN-DRAFT-WORK']);
    statusCatalogLine($draft);

    $this->get(route('operations.workboard'))
        ->assertOk()
        ->assertSee('Tech Operations')
        ->assertSee('TECH-ASSIGNED-BAY-WORK')
        ->assertDontSee('TECH-FOREIGN-BAY-WORK')
        ->assertDontSee('TECH-HIDDEN-DRAFT-WORK');
});

test('admins can preview bay view for a chosen technician', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $technician = User::factory()->create(['name' => 'Preview Tech'])->assignRole(ArkRole::Technician->value);
    $otherTech = User::factory()->create(['name' => 'Other Tech'])->assignRole(ArkRole::Technician->value);

    $assigned = statusCatalogRepairOrder(RepairOrderStatus::InProgress);
    $assigned->update(['concern_summary' => 'ADMIN-PREVIEW-MINE']);
    statusCatalogOwnedLine($assigned, $technician);

    $foreignBay = statusCatalogRepairOrder(RepairOrderStatus::InProgress);
    $foreignBay->update(['concern_summary' => 'ADMIN-PREVIEW-THEIRS']);
    statusCatalogOwnedLine($foreignBay, $otherTech);

    $this->actingAs($admin)
        ->get(route('operations.workboard', [
            'lens' => 'technician',
            'technician' => $technician->id,
        ]))
        ->assertOk()
        ->assertSee('ADMIN-PREVIEW-MINE')
        ->assertDontSee('ADMIN-PREVIEW-THEIRS');
});

test('technicians cannot widen workboard with advisor lens query', function () {
    $technician = actingAsLearnCurrentStaff(ArkRole::Technician);

    $draft = statusCatalogRepairOrder(RepairOrderStatus::Draft);
    $draft->update(['concern_summary' => 'TECH-LENS-OVERRIDE-HIDDEN']);
    statusCatalogLine($draft);

    $this->actingAs($technician)
        ->get(route('operations.workboard', ['lens' => 'advisor']))
        ->assertOk()
        ->assertDontSee('TECH-LENS-OVERRIDE-HIDDEN')
        ->assertDontSee('Lane detail');
});

test('existing v2 status strings remain valid after catalog seeding', function () {
    $repairOrder = statusCatalogRepairOrder(RepairOrderStatus::WaitingApproval);

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::WaitingApproval))->toBeTrue()
        ->and($repairOrder->statusDisplayLabel())->toBe('Waiting Approval');
});

test('admin can add a custom repair order status from settings', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $this->actingAs($admin);

    $this->from(route('operations.settings.shop.edit', ['section' => 'workflow', 'workflow-tab' => 'statuses']))
        ->patch(route('operations.settings.shop.status-catalog.update'), [
            'create' => [
                'name' => 'Sublet Pending',
                'slug' => 'sublet_pending',
                'advisor_lane_key' => 'shop_floor',
                'show_on_advisor_board' => '1',
                'show_on_technician_board' => '0',
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'workflow',
            'workflow-tab' => 'statuses',
        ]));

    app(RepairOrderStatusCatalog::class)->forgetCache();

    $catalog = app(RepairOrderStatusCatalog::class);

    expect(RepairOrderStatusDefinition::query()->where('slug', 'sublet_pending')->exists())->toBeTrue()
        ->and($catalog->labelForSlug('sublet_pending'))->toBe('Sublet Pending')
        ->and($catalog->allowedTargetSlugs('in_progress', actor: $admin))->toContain('sublet_pending')
        ->and(collect($catalog->advisorWorkboardLanes())->pluck('statuses')->flatten()->all())->toContain('sublet_pending');
});

test('advisor can retreat approved repair orders to waiting approval', function () {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $catalog = app(RepairOrderStatusCatalog::class);

    expect($catalog->canTransitionSlug(
        RepairOrderStatus::Approved->value,
        RepairOrderStatus::WaitingApproval->value,
        $advisor,
    ))->toBeTrue()
        ->and($catalog->allowedTargetSlugs(RepairOrderStatus::Approved->value, $advisor))
        ->toContain(RepairOrderStatus::WaitingApproval->value);
});

test('admin can add a custom lifecycle move from settings', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $this->actingAs($admin);

    $this->from(route('operations.settings.shop.edit', ['section' => 'workflow', 'workflow-tab' => 'statuses']))
        ->patch(route('operations.settings.shop.status-catalog.update'), [
            'create_transition' => [
                'from_slug' => RepairOrderStatus::Completed->value,
                'to_slug' => RepairOrderStatus::Estimate->value,
                'roles' => [ArkRole::Admin->value],
            ],
        ])
        ->assertRedirect(route('operations.settings.shop.edit', [
            'section' => 'workflow',
            'workflow-tab' => 'statuses',
        ]));

    app(RepairOrderStatusCatalog::class)->forgetCache();

    expect(app(RepairOrderStatusCatalog::class)->canTransitionSlug(
        RepairOrderStatus::Completed->value,
        RepairOrderStatus::Estimate->value,
        $admin,
    ))->toBeTrue();
});

test('job board mileage requirement blocks the move and names what is missing', function () {
    $advisor = actingAsLearnCurrentAdvisor();
    $technician = User::factory()->create(['name' => 'Bay Tech'])->assignRole(ArkRole::Technician->value);
    $this->actingAs($advisor);

    $repairOrder = statusCatalogRepairOrder(RepairOrderStatus::Approved);
    statusCatalogLine($repairOrder);
    $repairOrder->forceFill([
        'assigned_technician_id' => $technician->id,
        'mileage_in' => null,
        'mileage_out' => null,
    ])->save();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder), [
        'status' => RepairOrderStatus::InProgress->value,
    ])->assertRedirect()
        ->assertSessionHasErrors(['lifecycle' => 'Enter mileage in before moving to In Progress.']);

    $this->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->assertSee('Enter mileage in before moving to In Progress.', false)
        ->assertDontSee('value="in_progress" disabled', false);

    $repairOrder->forceFill([
        'mileage_in' => 44168,
        'status' => RepairOrderStatus::QualityCheck,
    ])->save();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder->fresh()), [
        'status' => RepairOrderStatus::Completed->value,
    ])->assertRedirect()
        ->assertSessionHasErrors(['lifecycle' => 'Enter mileage out before moving to Completed.']);

    $repairOrder->forceFill(['mileage_out' => 44200])->save();

    $this->patch(route('operations.repair-orders.lifecycle.update', $repairOrder->fresh()), [
        'status' => RepairOrderStatus::Completed->value,
    ])->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($repairOrder->fresh()->status->is(RepairOrderStatus::Completed))->toBeTrue();
});

test('job board settings save mileage requirements on a status', function () {
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);
    $this->actingAs($admin);

    $this->from(route('operations.settings.shop.edit', ['section' => 'workflow', 'workflow-tab' => 'statuses']))
        ->patch(route('operations.settings.shop.status-catalog.update'), [
            'statuses' => [
                RepairOrderStatus::InProgress->value => [
                    'name' => 'In Progress',
                    'show_on_advisor_board' => '1',
                    'show_on_technician_board' => '1',
                    'mileage_requirement' => 'both',
                ],
            ],
        ])
        ->assertRedirect();

    app(RepairOrderStatusCatalog::class)->forgetCache();

    $definition = RepairOrderStatusDefinition::query()
        ->where('slug', RepairOrderStatus::InProgress->value)
        ->firstOrFail();

    expect($definition->requires_mileage_in)->toBeTrue()
        ->and($definition->requires_mileage_out)->toBeTrue();
});

function statusCatalogRepairOrder(RepairOrderStatus $status): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Status',
        'last_name' => 'Catalog',
        'phone' => '555-0199',
        'email' => 'status-catalog@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'STS123',
        'year' => 2020,
        'make' => 'Subaru',
        'model' => 'Outback',
    ]);

    return RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'mileage_in' => 120000,
        'mileage_out' => 120050,
        'concern_summary' => 'Status catalog regression coverage.',
    ])->fresh();
}

function statusCatalogLine(RepairOrder $repairOrder): RepairOrderConcern
{
    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Inspection',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Inspect',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
        'subtotal_cents' => 10000,
        'total_cents' => 10000,
    ]);

    return $concern;
}

function statusCatalogOwnedLine(RepairOrder $repairOrder, User $technician): void
{
    $concern = statusCatalogLine($repairOrder);

    RepairOrderWorkGroup::query()->create([
        'repair_order_concern_id' => $concern->id,
        'title' => 'Inspection',
        'position' => 1,
        'owner_type' => RepairActionOwnerType::Technician,
        'owner_user_id' => $technician->id,
    ]);

    $repairOrder->forceFill(['assigned_technician_id' => $technician->id])->save();
}
