<?php

use App\Ark\Operations\Approvals\ApprovalSource;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Parts\LinkedPartsQuotes;
use App\Ark\Operations\RepairOrders\PartProcurementState;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderLine;
use App\Ark\Operations\RepairOrders\RepairOrderLineType;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Vehicles\Vehicle;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    session([WorkstationPresence::SESSION_BIND_DISMISSED => true]);
});

/** @return array{0: RepairOrder, 1: RepairOrderConcern, 2: RepairOrderLine, 3: RepairOrderLine} */
function panelsTablerRepairOrder(): array
{
    $customer = Customer::query()->create([
        'first_name' => 'Dana',
        'last_name' => 'Whitfield',
        'phone' => '555-0142',
        'email' => 'dana@example.test',
    ]);

    $vehicle = Vehicle::query()->create([
        'customer_id' => $customer->id,
        'plate' => 'PNL123',
        'year' => 2016,
        'make' => 'Toyota',
        'model' => 'Tacoma',
        'vin' => '3TMCZ5AN4GM012345',
    ]);

    $repairOrder = RepairOrder::query()->create([
        'customer_id' => $customer->id,
        'vehicle_id' => $vehicle->id,
        'status' => RepairOrderStatus::Estimate,
        'mileage_in' => 88000,
        'concern_summary' => 'Front brakes grinding.',
    ]);

    $concern = RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'position' => 1,
    ]);

    $part = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Part,
        'description' => 'Front brake pads',
        'quantity' => '1.00',
        'unit_price_cents' => 8068,
        'part_cost_cents' => 4000,
        'procurement_state' => PartProcurementState::Ordered,
        'subtotal_cents' => 8068,
        'total_cents' => 8068,
    ]);

    $labor = RepairOrderLine::query()->create([
        'repair_order_id' => $repairOrder->id,
        'repair_order_concern_id' => $concern->id,
        'type' => RepairOrderLineType::Labor,
        'description' => 'Replace front pads',
        'quantity' => '1.00',
        'unit_price_cents' => 15000,
        'subtotal_cents' => 15000,
        'total_cents' => 15000,
    ]);

    return [$repairOrder->fresh(), $concern, $part, $labor];
}

function panelsTablerXpath(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

function panelsTablerOne(DOMXPath $xpath, string $query, ?DOMNode $context = null): DOMElement
{
    $node = $xpath->query($query, $context)->item(0);

    expect($node)->toBeInstanceOf(DOMElement::class, "Missing {$query}");

    return $node;
}

/**
 * Legacy `ops-*` classes under $root that are not inside an .ark-legacy island nested in $root.
 *
 * @return list<string>
 */
function panelsTablerLegacyClasses(DOMElement $root): array
{
    $found = [];
    $walk = function (DOMElement $element, bool $inIsland) use (&$walk, &$found): void {
        $classes = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];
        $island = $inIsland || in_array('ark-legacy', $classes, true);

        if (! $island) {
            foreach ($classes as $class) {
                if (str_starts_with($class, 'ops-')) {
                    $found[] = $class;
                }
            }
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $walk($child, $island);
            }
        }
    };

    $walk($root, false);

    return array_values(array_unique($found));
}

function panelsTablerAdvisor(): User
{
    return User::factory()->create()->assignRole(ArkRole::Advisor->value);
}

test('the repair order workspace modal is a Tabler modal with every authoring panel and form', function (): void {
    [$repairOrder, $concern] = panelsTablerRepairOrder();

    $html = $this->actingAs(panelsTablerAdvisor())
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();
    $xpath = panelsTablerXpath($html);
    $host = panelsTablerOne($xpath, '//*[@id="workspace-modal-host"]');

    expect($host->getAttribute('x-data'))->toContain('arkWorkspaceModal(')
        ->and($host->getAttribute('@keydown.escape.window'))->toBe('onEscape()')
        ->and($host->getAttribute('@keydown.tab'))->toBe('trapFocus($event)')
        ->and(panelsTablerLegacyClasses($host))->toBe([])
        ->and($xpath->query('.//*[contains(@class, "modal-dialog")][@x-ref="dialog"]', $host)->length)->toBe(1)
        ->and($xpath->query('.//*[@id="workspace-modal-body"][contains(@class, "modal-body")]', $host)->length)->toBe(1)
        ->and($xpath->query('.//*[contains(@class, "modal-footer")]//button[@x-ref="primaryBtn"]', $host)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-workspace-modal-panel]', $host)->length)->toBeGreaterThan(10)
        ->and($xpath->query('.//*[contains(@class, "ops-workspace-modal__panel")]', $host)->length)->toBe(0);

    $chooser = panelsTablerOne($xpath, './/*[@data-workspace-add-work-chooser]', $host);
    $choices = [];
    foreach ($xpath->query('.//input[@type="radio"][@name="add_work_choice"][@x-model="addWorkChoice"]', $chooser) as $radio) {
        $choices[] = $radio->getAttribute('value');
    }
    expect($choices)->toBe(['concern', 'oil', 'testing', 'saved-work']);

    $forms = [
        'visit-reason' => route('operations.repair-orders.visit-reason.update', $repairOrder),
        'customer-identity' => route('operations.customers.update', $repairOrder->customer),
        'vehicle-identity' => route('operations.customers.vehicles.update', [$repairOrder->customer, $repairOrder->vehicle]),
        'visit-posture' => route('operations.repair-orders.visit-posture.update', $repairOrder),
        'mileage' => route('operations.repair-orders.mileage.update', $repairOrder),
        'oil' => route('operations.repair-orders.maintenance.engine-oil.store', $repairOrder),
        'testing' => route('operations.repair-orders.work-authorization.testing.store', $repairOrder),
        'saved-work' => route('operations.repair-orders.work-templates.apply', $repairOrder),
        'evidence' => route('operations.repair-orders.evidence.store', $repairOrder),
        'repair-action' => route('operations.repair-orders.concerns.work-groups.store', [$repairOrder, $concern]),
        'concern-narrative' => route('operations.repair-orders.concerns.update', [$repairOrder, $concern]),
        'line-create' => route('operations.repair-orders.lines.store', $repairOrder),
    ];

    foreach ($forms as $key => $action) {
        $form = panelsTablerOne($xpath, './/form[@data-workspace-modal-form="'.$key.'"]', $host);

        expect($form->getAttribute('action'))->toBe($action, $key)
            ->and(strtoupper($form->getAttribute('method')))->toBe('POST', $key)
            ->and($xpath->query('.//input[@name="_token"]', $form)->length)->toBe(1, $key);
    }

    $customerForm = panelsTablerOne($xpath, './/form[@data-workspace-modal-form="customer-identity"]', $host);
    $customerFields = [];
    foreach ($xpath->query('.//*[@name][not(@type="hidden")]', $customerForm) as $field) {
        $customerFields[] = $field->getAttribute('name');
    }
    expect($customerFields)->toBe(['first_name', 'last_name', 'customer_type', 'contact_preference', 'phone', 'email', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code'])
        ->and($xpath->query('.//input[contains(@class, "form-control")][@name="first_name"]', $customerForm)->length)->toBe(1);

    $vehicleForm = panelsTablerOne($xpath, './/form[@data-workspace-modal-form="vehicle-identity"]', $host);
    expect($xpath->query('.//*[@x-data][contains(@x-data, "arkVehicleDecode(")]//input[@name="vin"][contains(@class, "form-control")]', $vehicleForm)->length)->toBe(1);

    $lineCreate = panelsTablerOne($xpath, './/form[@data-workspace-modal-form="line-create"][@id="workspace-line-create-concern-'.$concern->id.'"]', $host);
    expect($lineCreate->getAttribute('@submit.prevent'))->toBe('submitLine($event)')
        ->and($lineCreate->getAttribute('data-refresh-scope'))->toBe('worksheet')
        ->and($xpath->query('.//input[@type="hidden"][@name="'.RepairOrderConcurrency::FIELD.'"]', $lineCreate)->length)->toBe(1)
        ->and($xpath->query('.//input[@type="hidden"][@name="repair_order_concern_id"][@value="'.$concern->id.'"]', $lineCreate)->length)->toBe(1);

    $lineEntry = panelsTablerOne($xpath, './/*[@data-line-entry-panel="simple"]', $lineCreate);

    expect($lineEntry->getAttribute('class'))->not->toContain('ark-legacy')
        ->and($xpath->query('.//*[@data-line-add]', $lineEntry)->length)->toBe(0)
        ->and($xpath->query('.//*[contains(@class, "ark-legacy")]', $host)->length)->toBe(0)
        ->and($xpath->query('.//*[@data-workspace-modal-panel][@x-show="task === \'document\'"]//*[@data-add-document-panel]', $host)->length)->toBe(1);
});

test('the estimate context panels render Tabler authorization, portal, and parts markup', function (): void {
    [$repairOrder, , $part] = panelsTablerRepairOrder();

    $html = $this->actingAs(panelsTablerAdvisor())
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();
    $xpath = panelsTablerXpath($html);

    $auth = panelsTablerOne($xpath, '//*[@id="authorization-rail-panel"]');
    $record = panelsTablerOne($xpath, './/form[@action="'.route('operations.repair-orders.authorization.store', $repairOrder).'"]', $auth);
    $recordFields = [];
    foreach ($xpath->query('.//*[@name][not(@type="hidden")]', $record) as $field) {
        $recordFields[] = $field->getAttribute('name');
    }

    expect(panelsTablerLegacyClasses($auth))->toBe([])
        ->and($xpath->query('.//*[contains(@class, "card-header")]//*[normalize-space()="Customer Authorization"]', $auth)->length)->toBe(1)
        ->and($record->getAttribute('data-refresh-scope'))->toBe('auth')
        ->and($record->getAttribute('data-continuity-focus'))->toBe("#financial-rail button[type='submit']")
        ->and($record->getAttribute('@submit.prevent'))->toBe('window.arkWorksheetFormSubmit($event)')
        ->and($xpath->query('.//input[@type="hidden"][@name="'.RepairOrderConcurrency::FIELD.'"]', $record)->length)->toBe(1)
        ->and($recordFields)->toBe(['source', 'approved_by', 'approved_amount', 'notes'])
        ->and($xpath->query('.//select[@name="source"][contains(@class, "form-select")]', $record)->length)->toBe(1)
        ->and($xpath->query('.//button[@type="submit"][contains(@class, "btn-primary")]', $record)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-authorization-history][contains(@class, "list-group")]', $auth)->length)->toBe(1);

    $portal = panelsTablerOne($xpath, '//*[@id="portal-rail"]');
    $estimateLink = panelsTablerOne($xpath, './/*[@data-portal-link="estimate"]', $portal);

    expect(panelsTablerLegacyClasses($portal))->toBe([])
        ->and($estimateLink->getAttribute('class'))->toBe('card-body')
        ->and($estimateLink->getAttribute('x-data'))->toContain('copyPortalLink()')
        ->and($xpath->query('.//a[@href="'.route('operations.repair-orders.portal-preview', $repairOrder).'"][contains(@class, "btn")]', $estimateLink)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-portal-activity]//*[contains(concat(" ", @class, " "), " list-group ")]', $portal)->length)->toBe(1);

    $parts = panelsTablerOne($xpath, '//*[@id="parts-rail"]');

    expect(panelsTablerLegacyClasses($parts))->toBe([])
        ->and($xpath->query('.//*[@data-parts-line="'.$part->id.'"]//span[contains(@class, "badge")][normalize-space()="Ordered"]', $parts)->length)->toBe(1)
        ->and($xpath->query('.//*[@data-parts-line="'.$part->id.'"]//button[normalize-space()="Print label"]', $parts)->length)->toBe(1);
});

test('the authorization history keeps the revoke form on Tabler markup', function (): void {
    [$repairOrder] = panelsTablerRepairOrder();
    $advisor = panelsTablerAdvisor();

    $this->actingAs($advisor)
        ->post(route('operations.repair-orders.authorization.store', $repairOrder), [
            'source' => ApprovalSource::InPerson->value,
            'approved_by' => 'Dana Whitfield',
        ])
        ->assertRedirect();

    $approval = $repairOrder->approvalEvents()->latest('id')->firstOrFail();
    $xpath = panelsTablerXpath($this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent());
    $auth = panelsTablerOne($xpath, '//*[@id="authorization-rail-panel"]');
    $revoke = panelsTablerOne($xpath, './/*[@data-authorization-revoke]//form[@action="'.route('operations.repair-orders.authorization.revoke', [$repairOrder, $approval]).'"]', $auth);
    $revokeFields = [];
    foreach ($xpath->query('.//*[@name][not(@type="hidden")]', $revoke) as $field) {
        $revokeFields[] = $field->getAttribute('name');
    }

    expect(panelsTablerLegacyClasses($auth))->toBe([])
        ->and($revoke->getAttribute('data-refresh-scope'))->toBe('auth')
        ->and($revoke->getAttribute('@submit.prevent'))->toBe('window.arkWorksheetFormSubmit($event)')
        ->and(array_values(array_unique($revokeFields)))->toBe(['concern_ids[]', 'revert_disposition', 'source', 'revoked_by', 'notes'])
        ->and($xpath->query('.//input[@name="concern_ids[]"][contains(@class, "form-check-input")][@checked]', $revoke)->length)->toBeGreaterThan(0);
});

test('the work exception dialog is a Tabler modal with the same record form', function (): void {
    [$repairOrder, $concern, $part, $labor] = panelsTablerRepairOrder();

    $html = $this->actingAs(panelsTablerAdvisor())
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();
    $xpath = panelsTablerXpath($html);
    $dialog = panelsTablerOne($xpath, '//*[@data-authorization-exception-dialog="'.$concern->id.'"]');
    $form = panelsTablerOne($xpath, './/form[@id="authorization-exception-form-'.$concern->id.'"]', $dialog);

    expect(panelsTablerLegacyClasses($dialog))->toBe([])
        ->and($dialog->getAttribute('x-show'))->toBe('open')
        ->and($xpath->query('.//*[contains(@class, "modal")][@role="dialog"][@aria-modal="true"]', $dialog)->length)->toBe(1)
        ->and($xpath->query('.//*[contains(@class, "modal-content")][@x-ref="dialog"][@tabindex="-1"]', $dialog)->length)->toBe(1)
        ->and($form->getAttribute('action'))->toBe(route('operations.repair-orders.concerns.authorization-exceptions.store', [$repairOrder, $concern]))
        ->and($form->getAttribute('@submit'))->toBe('submitRecord($event)')
        ->and($xpath->query('.//input[@type="hidden"][@name="authorization_exception_concern_id"][@value="'.$concern->id.'"]', $form)->length)->toBe(1)
        ->and($xpath->query('.//select[@name="reason"][contains(@class, "form-select")]', $form)->length)->toBe(1)
        ->and($xpath->query('.//input[@name="line_ids[]"][@value="'.$part->id.'"]', $form)->length)->toBe(1)
        ->and($xpath->query('.//input[@name="line_ids[]"][@value="'.$labor->id.'"]', $form)->length)->toBe(1)
        ->and($xpath->query('.//textarea[@name="note"][contains(@class, "form-control")]', $form)->length)->toBe(1)
        ->and($xpath->query('.//button[@type="submit"][@form="authorization-exception-form-'.$concern->id.'"]', $dialog)->length)->toBe(1);
});

test('the dealer quote capture and linked quote dialogs are Tabler modals', function (): void {
    [$repairOrder] = panelsTablerRepairOrder();
    $this->app->instance(LinkedPartsQuotes::class, new class implements LinkedPartsQuotes
    {
        public function available(): bool
        {
            return true;
        }

        public function present(RepairOrder $repairOrder): array
        {
            return ['available' => true, 'provider_label' => 'Example Catalog', 'open_note' => '', 'current' => null, 'history' => []];
        }

        public function refresh(RepairOrder $repairOrder, User $user): array
        {
            return ['ok' => true, 'message' => ''];
        }

        public function candidates(RepairOrder $repairOrder, User $user): array
        {
            return ['ok' => true, 'candidates' => []];
        }

        public function link(RepairOrder $repairOrder, User $user, string $externalId): array
        {
            return ['ok' => true, 'message' => ''];
        }

        public function makeCurrent(RepairOrder $repairOrder, User $user, string $linkId): array
        {
            return ['ok' => true, 'message' => ''];
        }
    });

    $html = $this->actingAs(panelsTablerAdvisor())
        ->get(route('operations.repair-orders.show', $repairOrder))
        ->assertOk()
        ->getContent();
    $xpath = panelsTablerXpath($html);

    $dealer = panelsTablerOne($xpath, '//*[@id="dealer-quote-capture"]');
    $dealerForm = panelsTablerOne($xpath, './/form[@data-dealer-quote-import-form]', $dealer);

    expect(panelsTablerLegacyClasses($dealer))->toBe([])
        ->and($dealer->getAttribute('x-data'))->toContain('arkDealerQuoteCapture(')
        ->and($xpath->query('.//*[contains(@class, "modal-dialog")]', $dealer)->length)->toBe(1)
        ->and($dealerForm->getAttribute(':action'))->toBe('importUrl')
        ->and($dealerForm->getAttribute('@submit'))->toBe('beforeSubmit($event)')
        ->and($xpath->query('.//input[@type="hidden"][@name="'.RepairOrderConcurrency::FIELD.'"]', $dealerForm)->length)->toBe(1)
        ->and($xpath->query('.//*[contains(@class, "table-responsive")]//table[contains(@class, "table")]', $dealerForm)->length)->toBe(1);

    $linked = panelsTablerOne($xpath, '//*[@data-linked-parts-quote]');

    expect(panelsTablerLegacyClasses($linked))->toBe([])
        ->and($linked->getAttribute('class'))->toContain('card')
        ->and($linked->getAttribute('x-data'))->toContain('arkLinkedPartsQuote(')
        ->and($xpath->query('.//*[@data-linked-parts-quote-dialog]//*[contains(@class, "modal-content")][@x-ref="dialog"]', $linked)->length)->toBe(1);
});

test('the PartsTech import panel renders Tabler markup with the same import form', function (): void {
    [$repairOrder] = panelsTablerRepairOrder();
    $this->actingAs(panelsTablerAdvisor());

    $html = view('operations.repair-orders.partials.repair-order-partstech-import', [
        'repairOrder' => $repairOrder,
        'estimateVersion' => $repairOrder->estimate_version,
        'partstechPoNumber' => 'RO'.$repairOrder->repair_order_id,
        'partstechPrepareUrl' => null,
        'canImportQuote' => true,
    ])->render();
    $xpath = panelsTablerXpath($html);
    $panel = panelsTablerOne($xpath, '//*[@id="partstech-quote-import"]');

    expect(panelsTablerLegacyClasses($panel))->toBe([])
        ->and($panel->getAttribute('x-data'))->toContain('arkPartsTechQuoteImport(')
        ->and($panel->getAttribute('@ark:partstech-pull-quote.window'))->toBe('loadQuote(false, $event.detail?.concernId)')
        ->and($html)->toContain(':action="importUrl" @submit="beforeSubmit($event)"')
        ->and($xpath->query('.//button[@id="partstech-import-submit-btn"][@type="submit"][contains(@class, "btn-primary")]', $panel)->length)->toBe(1);
});

test('the lazy workspace tabs render Tabler markup and keep their forms and hooks', function (): void {
    [$repairOrder] = panelsTablerRepairOrder();
    $advisor = panelsTablerAdvisor();
    $this->actingAs($advisor);

    $tab = fn (string $name): DOMXPath => panelsTablerXpath(
        $this->get(route('operations.repair-orders.workspace-tabs.show', ['repairOrder' => $repairOrder, 'tab' => $name]))
            ->assertOk()
            ->getContent(),
    );

    $comms = $tab('comms');
    $commsRoot = panelsTablerOne($comms, '//*[@id="communication-rail"]');
    $thread = panelsTablerOne($comms, '//*[@id="conversation-messages-ro"]');
    $workflow = panelsTablerOne($comms, '//*[@id="comms-workflow-log"]//form');
    $commitment = panelsTablerOne($comms, '//*[@data-repair-order-commitments]//form[@action="'.route('operations.repair-orders.commitments.store', $repairOrder).'"]');

    expect(panelsTablerLegacyClasses($commsRoot))->toBe([])
        ->and($thread->getAttribute('class'))->toContain('ark-legacy')
        ->and($thread->getAttribute('data-timeline-refresh'))->toBe('comms-tab')
        ->and($comms->query('//*[@data-comms-composer]')->length)->toBe(1)
        ->and($comms->query('//*[@data-comms-composer][contains(@class, "ark-legacy")]')->length)->toBe(0)
        ->and($workflow->getAttribute('action'))->toBe(route('operations.repair-orders.communications.store', $repairOrder))
        ->and($workflow->getAttribute('data-refresh-scope'))->toBe('comms')
        ->and($comms->query('.//select[@name="communication_type"][contains(@class, "form-select")]', $workflow)->length)->toBe(1)
        ->and($comms->query('.//textarea[@name="due_at"] | .//input[@name="due_at"][@type="datetime-local"]', $commitment)->length)->toBe(1)
        ->and($comms->query('.//input[@type="hidden"][@name="type"]', $commitment)->length)->toBe(1);

    $history = $tab('history');
    $historyRoot = panelsTablerOne($history, '//*[@id="history-rail"]');

    expect(panelsTablerLegacyClasses($historyRoot))->toBe([])
        ->and($history->query('//*[@data-history-prior-visits]//*[contains(concat(" ", @class, " "), " list-group ")]')->length)->toBe(1);

    $inspect = $tab('inspect');

    expect(panelsTablerLegacyClasses(panelsTablerOne($inspect, '//*[@id="inspect-rail"][@data-inspection-control]')))->toBe([]);

    $recommendations = $tab('recommendations');
    $recommendationsRoot = panelsTablerOne($recommendations, '//*[@id="recommendations-rail"]');

    expect(panelsTablerLegacyClasses($recommendationsRoot))->toBe([])
        ->and($recommendations->query('//form[@data-recommendation-create][@action="'.route('operations.repair-orders.recommendations.store', $repairOrder).'"]//input[@name="title"][contains(@class, "form-control")]')->length)->toBe(1);
});

test('the review request panel renders Tabler buttons in the workspace modal', function (): void {
    [$repairOrder] = panelsTablerRepairOrder();
    $this->actingAs(panelsTablerAdvisor());

    $html = view('operations.repair-orders.partials.repair-order-review-request-panel', [
        'repairOrder' => $repairOrder,
        'estimateVersion' => $repairOrder->estimate_version,
        'closePaid' => true,
        'modal' => true,
    ])->render();
    $xpath = panelsTablerXpath('<div id="panel">'.$html.'</div>');
    $panel = panelsTablerOne($xpath, '//*[@id="panel"]');
    $closePaid = $xpath->query('.//form[@action="'.route('operations.repair-orders.lifecycle.update', $repairOrder).'"][@data-workspace-modal-form="review-request"]', $panel);

    expect(panelsTablerLegacyClasses($panel))->toBe([])
        ->and($closePaid->length)->toBeGreaterThan(0)
        ->and($xpath->query('.//input[@type="hidden"][@name="status"][@value="closed:paid"]', $closePaid->item(0))->length)->toBe(1)
        ->and($xpath->query('.//button[@type="submit"][contains(@class, "btn")]', $panel)->length)->toBeGreaterThan(0);
});
