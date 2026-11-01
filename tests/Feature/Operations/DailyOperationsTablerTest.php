<?php

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Inspections\ApplyInspectionTemplateAction;
use App\Ark\Operations\Inspections\DefaultInspectionTemplateCatalog;
use App\Ark\Operations\Inspections\EnsureInspectionAction;
use App\Ark\Operations\Maintenance\MaintenanceService;
use App\Ark\Operations\RepairOrders\RepairActionOwnerType;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\RepairOrderWorkGroup;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);

    $this->app->instance(Vite::class, new class extends Vite
    {
        public function __invoke($entrypoints, $buildDirectory = null)
        {
            return new HtmlString('<!-- vite: '.implode(',', (array) $entrypoints).' -->');
        }
    });
});

/**
 * The page content inside the Tabler shell: no legacy island and no legacy class.
 */
function dailyOpsTablerMain(TestResponse $response): string
{
    $html = $response->assertOk()
        ->assertSee('<!-- vite: resources/css/app.css,resources/css/tabler.css,resources/js/app.js,resources/js/tabler.js -->', false)
        ->assertSee('navbar navbar-vertical', false)
        ->assertDontSee('class="ops-left-rail"', false)
        ->getContent();

    $start = strpos($html, '<main>');
    $end = strrpos($html, '</main>');

    expect($start)->not->toBeFalse()
        ->and($end)->not->toBeFalse();

    return substr($html, $start, $end - $start);
}

function expectNoLegacyMarkup(string $main): void
{
    expect($main)->not->toContain('ark-legacy')
        ->and($main)->not->toMatch('/class="[^"]*(?<![\w-])ops-[a-z]/');
}

function dailyOpsRepairOrder(?User $technician = null, RepairOrderStatus $status = RepairOrderStatus::InProgress): RepairOrder
{
    $customer = Customer::query()->create([
        'first_name' => 'Daily',
        'last_name' => 'Operations',
        'phone' => '7195550142',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'DAILY1',
        'year' => 2016,
        'make' => 'Toyota',
        'model' => 'Tacoma',
        'vin' => '3TMCZ5AN1GM012345',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => $status,
        'mileage_in' => 98000,
        'concern_summary' => 'DAILY-OPS-BAY-WORK',
        'visit_reason' => 'Brakes grinding on stop.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Brakes grinding',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $repairOrder->lines()->create([
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Inspect brakes',
        'quantity' => '1.00',
        'unit_price_cents' => 10000,
        'subtotal_cents' => 10000,
        'total_cents' => 10000,
    ]);

    if ($technician !== null) {
        RepairOrderWorkGroup::query()->create([
            'repair_order_concern_id' => $concern->id,
            'title' => 'Brakes',
            'position' => 1,
            'owner_type' => RepairActionOwnerType::Technician,
            'owner_user_id' => $technician->id,
        ]);

        $repairOrder->forceFill(['assigned_technician_id' => $technician->id])->save();
    }

    return $repairOrder->fresh(['vehicle', 'customer', 'concerns']);
}

test('the technician Job Board renders lanes and cards in the Tabler shell', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = dailyOpsRepairOrder($technician);

    $main = dailyOpsTablerMain($this->actingAs($technician)->get(route('operations.workboard')));

    expectNoLegacyMarkup($main);
    expect($main)->toContain('class="page-title">Job Board')
        ->toContain('class="ark-board pb-2" data-technician-board')
        ->toContain('data-technician-lane=')
        ->toContain('data-technician-board-card')
        ->toContain('DAILY-OPS-BAY-WORK')
        ->toContain(route('operations.repair-orders.show', $repairOrder))
        ->toContain('data-inspection-capture-cta');
});

test('the Job Board keeps its role access', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($technician)->get(route('operations.workboard'))->assertOk();
    $this->actingAs($advisor)->get(route('operations.workboard'))->assertRedirect(route('operations.index'));
    $this->actingAs($admin)->get(route('operations.workboard'))->assertRedirect(route('operations.index'));

    $main = dailyOpsTablerMain($this->actingAs($admin)->get(route('operations.workboard', [
        'lens' => 'technician',
        'technician' => $technician->id,
    ])));

    expectNoLegacyMarkup($main);
    expect($main)->toContain('data-technician-board');
});

test('the technician landing renders in the Tabler shell with its inspection entry', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = dailyOpsRepairOrder($technician);

    $main = dailyOpsTablerMain($this->actingAs($technician)->get(route('operations.repair-orders.show', $repairOrder)));

    expectNoLegacyMarkup($main);
    expect($main)->toContain('data-technician-production-landing')
        ->toContain('data-tech-why-here')
        ->toContain('data-tech-concerns')
        ->toContain('data-inspection-entry')
        ->toContain('data-inspection-cta')
        ->toContain('data-inspection-capture-cta')
        ->toContain('data-desktop-walk-url=')
        ->toContain('data-tablet-url=')
        ->toContain('data-inspection-tablet')
        ->toContain('data-inspection-desk');
});

test('the inspection section walk renders in the Tabler shell and keeps its hooks', function (?User $viewer, bool $canRecord): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = dailyOpsRepairOrder($technician);
    app(EnsureInspectionAction::class)->execute($repairOrder, $technician);

    $viewer ??= $technician;

    $main = dailyOpsTablerMain($this->actingAs($viewer)->get(route('operations.repair-orders.inspection.show', $repairOrder)));

    expectNoLegacyMarkup($main);
    expect($main)->toContain('data-inspection-surface="standard"')
        ->toContain('data-inspection-coverage')
        ->toContain('id="inspection-sections"')
        ->toContain('x-data="arkInspectionSectionWalk(')
        ->toContain('x-on:click="toggleSection(section.key)"')
        ->toContain('x-on:click="retry(point)"')
        ->toContain('Other Findings')
        ->toContain('action="'.route('operations.repair-orders.inspection.findings.store', $repairOrder).'"')
        ->toContain('name="label"')
        ->toContain('name="measurement_value"')
        ->toContain('name="measurement_unit"')
        ->toContain('name="photo"')
        ->toContain('name="notes"')
        ->toContain('name="intent"')
        ->toContain('x-on:ark-open-finding-capture.window="openCapture()"');

    if ($canRecord) {
        expect($main)->toContain('data-inspection-finding-btn')
            ->toContain('x-on:click="setCondition(point, option.value)"')
            ->toContain('x-on:click="toggleObservation(point, option.key)"')
            ->toContain('x-on:input="scheduleNote(point)"')
            ->toContain('name="purpose"');
    } else {
        expect($main)->not->toContain('data-inspection-finding-btn')
            ->toContain('recording is limited to the assigned technician or an advisor');
    }
})->with([
    'assigned technician' => [fn () => null, true],
    'advisor' => [fn () => User::factory()->create()->assignRole(ArkRole::Advisor->value), true],
    'admin' => [fn () => User::factory()->create()->assignRole(ArkRole::Admin->value), true],
    'other technician' => [fn () => User::factory()->create()->assignRole(ArkRole::Technician->value), false],
]);

test('the inspection point walk renders in the Tabler shell with its evidence and upload forms', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = dailyOpsRepairOrder($technician);
    $inspection = app(EnsureInspectionAction::class)->execute($repairOrder, $technician);
    DefaultInspectionTemplateCatalog::seedIfMissing();
    app(ApplyInspectionTemplateAction::class)->execute($repairOrder, $inspection, actor: $technician);
    $item = $inspection->items()->where('label', 'LF Tire')->firstOrFail();
    $item->photos()->create([
        'storage_path' => 'inspection-tests/daily-ops.jpg',
        'content_type' => 'image/jpeg',
        'purpose' => 'internal',
        'original_name' => 'daily-ops.jpg',
        'byte_size' => 1200,
    ]);

    $main = dailyOpsTablerMain($this->actingAs($technician)->get(route('operations.repair-orders.inspection.show', [
        'repairOrder' => $repairOrder,
        'point' => $item->id,
    ])));

    expectNoLegacyMarkup($main);
    expect($main)->toContain('id="inspection-walk"')
        ->toContain('x-data="arkInspectionWalk(')
        ->toContain('x-on:click="setCondition(')
        ->toContain('x-on:change.debounce.500ms="saveNote()"')
        ->toContain('aria-label="Inspection walk order"')
        ->toContain('data-ops-lightbox=')
        ->toContain('onsubmit="return confirm(\'Remove this photo?\');"')
        ->toContain('name="_method" value="delete"')
        ->toContain('action="'.route('operations.repair-orders.inspection.photos.store', [$repairOrder, $item->id]).'"')
        ->toContain('capture="environment"')
        ->toContain('onchange="this.form.submit()"')
        ->toContain('Back to sections');
});

test('the inspection tablet surface keeps its own bay layout', function (): void {
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);
    $repairOrder = dailyOpsRepairOrder($technician);
    app(EnsureInspectionAction::class)->execute($repairOrder, $technician);

    $this->actingAs($technician)
        ->get(route('operations.repair-orders.inspection.show', ['repairOrder' => $repairOrder, 'surface' => 'tablet']))
        ->assertOk()
        ->assertSee('ops-inspection-tablet-shell', false)
        ->assertSee('ops-inspection-sections--tablet', false)
        ->assertDontSee('tabler.css', false);
});

test('engine oil confirmation renders in the Tabler shell with the same form', function (): void {
    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $repairOrder = dailyOpsRepairOrder();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.maintenance.engine-oil.store', $repairOrder), ['reset_reminder' => '1'])
        ->assertRedirect();

    $service = MaintenanceService::query()->where('repair_order_id', $repairOrder->id)->firstOrFail();
    $url = route('operations.repair-orders.maintenance.confirm', [$repairOrder, $service]);

    $main = dailyOpsTablerMain($this->actingAs($advisor)->get($url));

    expectNoLegacyMarkup($main);
    expect($main)->toContain('action="'.route('operations.repair-orders.maintenance.confirm.store', [$repairOrder, $service]).'"')
        ->toContain('name="_token"')
        ->toContain('name="oil_brand"')
        ->toContain('list="oil-brand-suggestions"')
        ->toContain('name="viscosity"')
        ->toContain('name="quantity_qt"')
        ->toContain('name="filter_part"')
        ->toContain('name="washer"')
        ->toContain('name="service_mileage"')
        ->toContain('name="reset_reminder"')
        ->toContain('Confirm Installed');

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $technician = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $this->actingAs($admin)->get($url)->assertOk();
    $this->actingAs($technician)->get($url)->assertForbidden();
});

test('new contact renders in the Tabler shell with the same form', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $url = route('operations.ingress.create-contact', ['phone' => '7195550177']);

    $main = dailyOpsTablerMain($this->actingAs($advisor)->get($url));

    expectNoLegacyMarkup($main);
    expect($main)->toContain('action="'.route('operations.ingress.create-contact.store').'"')
        ->toContain('name="return_url"')
        ->toContain('id="ingress-contact-first-name"')
        ->toContain('id="ingress-contact-last-name"')
        ->toContain('id="ingress-contact-phone"')
        ->toContain('id="ingress-contact-email"')
        ->toContain('id="ingress-contact-preference"')
        ->toContain('id="ingress-contact-referral"')
        ->toContain('id="ingress-contact-type"')
        ->toContain('Save contact');

    expect(strpos($main, 'name="first_name"'))->toBeLessThan(strpos($main, 'name="last_name"'))
        ->and(strpos($main, 'name="phone"'))->toBeLessThan(strpos($main, 'name="email"'))
        ->and(strpos($main, 'name="contact_preference"'))->toBeLessThan(strpos($main, 'name="referral_source"'))
        ->and(strpos($main, 'name="referral_source"'))->toBeLessThan(strpos($main, 'name="customer_type"'));

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))->get($url)->assertOk();
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Technician->value))->get($url)->assertForbidden();
});

test('caller lookup renders in the Tabler shell and keeps the context panel in an island', function (): void {
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    dailyOpsRepairOrder();

    $main = dailyOpsTablerMain($this->actingAs($advisor)->get(route('operations.caller-lookup')));

    expectNoLegacyMarkup($main);
    expect($main)->toContain('action="'.route('operations.caller-lookup').'"')
        ->toContain('id="caller-phone"')
        ->toContain('name="phone"')
        ->toContain('Lookup Caller');

    $matched = dailyOpsTablerMain($this->actingAs($advisor)->get(route('operations.caller-lookup', ['phone' => '719-555-0142'])));

    expect($matched)->toContain('data-caller-lookup-match')
        ->and(substr_count($matched, 'class="ark-legacy bg-white text-dark"'))->toBe(1);

    $unmatched = dailyOpsTablerMain($this->actingAs($advisor)->get(route('operations.caller-lookup', ['phone' => '719-555-0999'])));

    expectNoLegacyMarkup($unmatched);
    expect($unmatched)->toContain('No customer matched')
        ->toContain('No conversation history on this number yet.');

    foreach ([ArkRole::Admin, ArkRole::Technician] as $role) {
        $this->actingAs(User::factory()->create()->assignRole($role->value))
            ->get(route('operations.caller-lookup'))
            ->assertOk();
    }
});

test('operational observations render in the Tabler shell for admins only', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $main = dailyOpsTablerMain($this->actingAs($admin)->get(route('operations.observations.index')));

    expectNoLegacyMarkup($main);
    expect($main)->toContain('data-operational-observations')
        ->toContain('Operational observations');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Advisor->value))
        ->get(route('operations.observations.index'))
        ->assertForbidden();
    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Technician->value))
        ->get(route('operations.observations.index'))
        ->assertForbidden();
});

test('profile renders in the Tabler shell with every tab form for every role', function (ArkRole $role): void {
    $user = User::factory()->create()->assignRole($role->value);

    $main = dailyOpsTablerMain($this->actingAs($user)->get(route('profile.edit')));

    expectNoLegacyMarkup($main);
    expect($main)->toContain('x-on:change="setTab($event.target.value)"')
        ->toContain('role="tablist"')
        ->toContain('aria-controls="profile-panel-workstation-pin"')
        ->toContain('role="tabpanel"')
        ->toContain('action="'.route('profile.update').'"')
        ->toContain('action="'.route('verification.send').'"')
        ->toContain('action="'.route('profile.appearance.update').'"')
        ->toContain('action="'.route('password.update').'"')
        ->toContain('action="'.route('profile.partstech.update').'"')
        ->toContain('action="'.route('profile.catalogs.update').'"')
        ->toContain('name="accent_theme"')
        ->toContain('name="accent_color"')
        ->toContain('name="display_theme"')
        ->toContain('name="current_password"')
        ->toContain('name="password_confirmation"')
        ->toContain('name="partstech_username"')
        ->toContain('name="default_parts_catalog"')
        ->toContain('name="repairlink_color"')
        ->toContain('name="create[color]"');

    foreach (['profile', 'appearance', 'password', 'workstation-pin', 'partstech', 'catalogs'] as $tab) {
        expect($main)->toContain('x-show="tab === \''.$tab.'\'"');
    }
})->with([
    'advisor' => [ArkRole::Advisor],
    'technician' => [ArkRole::Technician],
    'admin' => [ArkRole::Admin],
]);

test('the station PIN form keeps its digit filter hook', function (): void {
    $user = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $user->setOperatorPin('1234');
    $user->save();

    $html = $this->actingAs($user)->get(route('profile.edit', ['tab' => 'workstation-pin']))->assertOk()->getContent();

    expect($html)->toContain('action="'.route('profile.workstation-pin.update').'"')
        ->toContain('data-profile-workstation-pin-form')
        ->toContain("document.querySelectorAll('[data-profile-workstation-pin-form] input[name=\"pin\"]");
});

test('the staff time clock history renders in the Tabler shell and keeps every form and permission', function (): void {
    $technician = User::factory()->create(['name' => 'Daily Clock'])->assignRole(ArkRole::Technician->value);
    completeRequiredLearnFor($technician);
    $admin = actingAsLearnCurrentStaff(ArkRole::Admin);

    $this->actingAs($admin)
        ->post(route('operations.time-clock.staff.in', $technician))
        ->assertRedirect(route('operations.time-clock.staff', $technician));

    $session = \App\Ark\Operations\Labor\TechnicianTimeSession::query()->where('user_id', $technician->id)->firstOrFail();
    $url = route('operations.time-clock.staff', $technician);

    $main = dailyOpsTablerMain($this->actingAs($admin)->get($url));

    expectNoLegacyMarkup($main);
    expect($main)->toContain('Punch history')
        ->toContain('Tech production')
        ->toContain('action="'.route('operations.time-clock.staff.out', $technician).'"')
        ->toContain('name="lunch" value="1"')
        ->toContain('action="'.route('operations.time-clock.staff.auto-clock', $technician).'"')
        ->toContain('name="auto_clock_enabled"')
        ->toContain('name="auto_lunch_minutes"')
        ->toContain('action="'.route('operations.time-clock.correct', $session).'"')
        ->toContain('name="clocked_in_at"')
        ->toContain('name="clocked_out_at"')
        ->toContain('action="'.route('operations.time-clock.delete', $session).'"')
        ->toContain("onsubmit=\"return confirm('Delete this punch? It will no longer count toward compensable hours.')\"")
        ->toContain('x-show="panelId === '.$session->id.' && panelMode === \'correct\'"')
        ->toContain('x-show="panelId === '.$session->id.' && panelMode === \'delete\'"');

    $advisor = actingAsLearnCurrentStaff(ArkRole::Advisor);
    $advisorMain = dailyOpsTablerMain($this->actingAs($advisor)->get($url));

    expectNoLegacyMarkup($advisorMain);
    expect($advisorMain)->toContain('action="'.route('operations.time-clock.staff.out', $technician).'"')
        ->not->toContain('Tech production')
        ->not->toContain('action="'.route('operations.time-clock.correct', $session).'"')
        ->not->toContain('action="'.route('operations.time-clock.delete', $session).'"')
        ->not->toContain('name="auto_clock_enabled"');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Technician->value))->get($url)->assertForbidden();
});

test('ARK Cloud connecting renders in the Tabler shell and keeps its poller', function (): void {
    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $main = dailyOpsTablerMain($this->actingAs($admin)->get(route('operations.cloud.connecting')));

    expectNoLegacyMarkup($main);
    expect($main)->toContain('id="cloud-connect-status"')
        ->toContain("document.getElementById('cloud-connect-status')")
        ->toContain(json_encode(route('operations.cloud.poll')))
        ->toContain('action="'.route('operations.settings.shop.ark-cloud.claim').'"')
        ->toContain('Check now');
});

test('Platform clusters render in the Tabler shell for master admins only', function (): void {
    $master = User::factory()->create(['is_master_admin' => true])->assignRole(ArkRole::Admin->value);

    $main = dailyOpsTablerMain($this->actingAs($master)->get(route('platform.clusters.index')));

    expectNoLegacyMarkup($main);
    expect($main)->toContain('data-platform-clusters')
        ->toContain('table-responsive');

    $this->actingAs(User::factory()->create()->assignRole(ArkRole::Admin->value))
        ->get(route('platform.clusters.index'))
        ->assertForbidden();
});

test('the 409 page renders in the Tabler shell', function (): void {
    \Illuminate\Support\Facades\Route::middleware('web')->get('/_tests/estimate-conflict', fn () => response()->view('errors.409', [
        'message' => 'DAILY-OPS-CONFLICT',
    ], 409));

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $response = $this->actingAs($advisor)->get('/_tests/estimate-conflict')->assertStatus(409);
    $html = $response->getContent();
    $main = substr($html, strpos($html, '<main>'), strrpos($html, '</main>') - strpos($html, '<main>'));

    expect($html)->toContain('navbar navbar-vertical');
    expectNoLegacyMarkup($main);
    expect($main)->toContain('DAILY-OPS-CONFLICT')
        ->toContain('Review Latest State')
        ->toContain('href="'.route('operations.index').'"');
});
