<?php

use App\Ark\Operations\RepairOrders\ConcernBillingPosture;
use App\Ark\Operations\RepairOrders\RecommendationPriority;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderConcurrency;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Database\Seeders\ShopSettingsSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->seed(ShopSettingsSeeder::class);
});

function billingControlConcern($repairOrder, ConcernBillingPosture $posture = ConcernBillingPosture::Default): RepairOrderConcern
{
    return RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'disposition' => RepairOrderConcernDisposition::Recommended,
        'recommendation_priority' => RecommendationPriority::MustDo->value,
        'billing_posture' => $posture,
        'position' => 1,
    ]);
}

function billingControlXPath(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

/** The option's opening tag as rendered. HTML parsers differ on Alpine's @click attribute name. */
function billingControlOptionTag(string $html, string $value): string
{
    preg_match('/<button\b[^>]*\bdata-billing-option="'.preg_quote($value, '/').'"[^>]*>/s', $html, $match);

    expect($match)->not->toBeEmpty();

    return $match[0];
}

test('the concern header shows the short billing choice with its details in the tooltip', function (): void {
    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Advisor));
    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = billingControlConcern($repairOrder);
    $presentation = ShopSettings::current()->billingPostureOptionPresentation(ConcernBillingPosture::Default);

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent();
    $xpath = billingControlXPath($html);
    $toggle = $xpath->query('//*[@id="concern-'.$concern->id.'"]//*[@data-concern-billing-choices]/button')->item(0);

    expect($toggle)->toBeInstanceOf(DOMElement::class)
        ->and(trim($toggle->textContent))->toBe('Shop default')
        ->and($toggle->getAttribute('data-billing-current'))->toBe('default')
        ->and($toggle->getAttribute('title'))->toBe($presentation['title'])
        ->and($toggle->getAttribute('aria-label'))->toContain('Shop default, $'.$presentation['rate'].'/hr · '.$presentation['matrix_name'])
        ->and($html)->not->toContain("task: 'concern-billing'")
        ->and($html)->not->toContain('data-workspace-modal-form="concern-billing"');
});

test('the billing dropdown lists every selectable choice and submits only a different one', function (): void {
    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Advisor));
    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = billingControlConcern($repairOrder, ConcernBillingPosture::Fleet);

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent();
    $xpath = billingControlXPath($html);
    $dropdown = $xpath->query('//*[@id="concern-'.$concern->id.'"]//*[@data-concern-billing-choices]')->item(0);
    $form = $xpath->query('ancestor::form', $dropdown)->item(0);

    $options = [];
    foreach ($xpath->query('.//*[@data-billing-option]', $dropdown) as $option) {
        $options[$option->getAttribute('data-billing-option')] = $option;
    }

    expect($form->getAttribute('action'))->toBe(route('operations.repair-orders.concerns.billing-posture', [$repairOrder, $concern]))
        ->and($xpath->query('.//input[@name="billing_posture"]', $form)->item(0)->getAttribute('value'))->toBe('fleet')
        ->and($xpath->query('.//input[@name="'.RepairOrderConcurrency::FIELD.'"]', $form)->length)->toBe(1)
        ->and(array_keys($options))->toBe(array_map(
            fn (ConcernBillingPosture $posture): string => $posture->value,
            ConcernBillingPosture::advisorSelectableCases(),
        ))
        ->and($options['fleet']->getAttribute('aria-current'))->toBe('true')
        ->and($xpath->query('.//*[@aria-current="true"]', $dropdown)->length)->toBe(1)
        ->and(billingControlOptionTag($html, 'fleet'))->not->toContain('requestSubmit()')
        ->and(billingControlOptionTag($html, 'internal'))->toContain("billing_posture.value = 'internal'")
        ->and(billingControlOptionTag($html, 'internal'))->toContain('requestSubmit()')
        ->and($options['internal']->textContent)->toContain('Internal');
});

test('choosing a billing option saves through the billing route with the form concurrency token', function (): void {
    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Advisor));
    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = billingControlConcern($repairOrder);

    $xpath = billingControlXPath($this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent());
    $form = $xpath->query('//*[@id="concern-'.$concern->id.'"]//*[@data-concern-billing-choices]/ancestor::form')->item(0);
    $fields = [];
    foreach ($xpath->query('.//input[@type="hidden"]', $form) as $input) {
        $fields[$input->getAttribute('name')] = $input->getAttribute('value');
    }

    $this->patch($form->getAttribute('action'), [...$fields, 'billing_posture' => ConcernBillingPosture::WarrantyOther->value])
        ->assertRedirect();

    expect($concern->fresh()->billing_posture)->toBe(ConcernBillingPosture::WarrantyOther);

    $this->patch($form->getAttribute('action'), [...$fields, 'billing_posture' => ConcernBillingPosture::Fleet->value])
        ->assertStatus(409);

    expect($concern->fresh()->billing_posture)->toBe(ConcernBillingPosture::WarrantyOther);
});

test('a closed repair order shows billing without the dropdown', function (): void {
    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Advisor));
    $repairOrder = repairOrderForEstimateWorkspace();
    billingControlConcern($repairOrder, ConcernBillingPosture::Fleet);
    $repairOrder->forceFill(['status' => RepairOrderStatus::Closed])->saveQuietly();

    $html = $this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent();

    expect($html)->not->toContain('data-concern-billing-choices')
        ->and($html)->not->toContain('data-billing-option');
});
