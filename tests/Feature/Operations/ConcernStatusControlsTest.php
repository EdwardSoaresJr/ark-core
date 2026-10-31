<?php

use App\Ark\Operations\RepairOrders\RecommendationPresentation;
use App\Ark\Operations\RepairOrders\RecommendationPriority;
use App\Ark\Operations\RepairOrders\RepairOrderConcern;
use App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition;
use App\Ark\Operations\RepairOrders\RepairOrderStatus;
use App\Ark\Operations\RepairOrders\ScopeProductionStatus;
use App\Ark\Runtime\Authorization\ArkRole;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Database\Seeders\ShopSettingsSeeder;

beforeEach(function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $this->seed(RepairOrderStatusCatalogSeeder::class);
    $this->seed(ShopSettingsSeeder::class);
});

function statusControlsConcern($repairOrder, RepairOrderConcernDisposition $disposition): RepairOrderConcern
{
    return RepairOrderConcern::query()->create([
        'repair_order_id' => $repairOrder->id,
        'summary' => 'Front brakes',
        'disposition' => $disposition,
        'recommendation_priority' => RecommendationPriority::MustDo->value,
        'production_status' => ScopeProductionStatus::WaitingParts,
        'position' => 1,
    ]);
}

function statusControlsXPath(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

test('priority, customer decision, and work status are three dropdowns in that order', function (): void {
    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Advisor));
    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = statusControlsConcern($repairOrder, RepairOrderConcernDisposition::Recommended);

    $xpath = statusControlsXPath($this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent());
    $controls = $xpath->query('//*[@id="concern-'.$concern->id.'"]//*[@data-concern-status-controls]')->item(0);

    expect($controls)->toBeInstanceOf(DOMElement::class);

    $order = [];
    foreach ($xpath->query('.//*[@data-concern-priority-choices or @data-concern-decision-choices or @data-concern-work-status-choices]', $controls) as $dropdown) {
        $order[] = match (true) {
            $dropdown->hasAttribute('data-concern-priority-choices') => 'priority',
            $dropdown->hasAttribute('data-concern-decision-choices') => 'decision',
            default => 'work-status',
        };
    }

    $decisionToggle = $xpath->query('.//*[@data-concern-decision-choices]/button', $controls)->item(0);
    $workToggle = $xpath->query('.//*[@data-concern-work-status-choices]/button', $controls)->item(0);

    expect($order)->toBe(['priority', 'decision', 'work-status'])
        ->and(trim($decisionToggle->textContent))->toBe('Pending Approval')
        ->and($decisionToggle->getAttribute('data-disposition-tone'))->toBe('recommended')
        ->and(trim($workToggle->textContent))->toBe('Waiting parts')
        ->and($xpath->query('//*[@data-concern-decision="recommended"]')->length)->toBe(0);
});

test('the decision dropdown offers every disposition and posts only a changed one', function (): void {
    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Advisor));
    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = statusControlsConcern($repairOrder, RepairOrderConcernDisposition::Approved);

    $xpath = statusControlsXPath($this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent());
    $dropdown = $xpath->query('//*[@id="concern-disposition-'.$concern->id.'"]//*[@data-concern-decision-choices]')->item(0);
    $form = $xpath->query('ancestor::form', $dropdown)->item(0);

    $options = [];
    foreach ($xpath->query('.//*[@data-disposition-option]', $dropdown) as $option) {
        $options[$option->getAttribute('data-disposition-option')] = $option;
    }

    expect($form->getAttribute('action'))->toBe(route('operations.repair-orders.concerns.disposition', [$repairOrder, $concern]))
        ->and($xpath->query('.//input[@name="disposition"]', $form)->item(0)->getAttribute('value'))->toBe('approved')
        ->and(array_keys($options))->toBe(array_map(
            fn (RepairOrderConcernDisposition $disposition): string => $disposition->value,
            RepairOrderConcernDisposition::cases(),
        ))
        ->and(trim($options['recommended']->textContent))->toBe('Pending Approval')
        ->and($options['approved']->getAttribute('aria-current'))->toBe('true')
        ->and($options['approved']->hasAttribute('@click'))->toBeFalse()
        ->and($options['declined']->getAttribute('@click'))->toContain("disposition.value = 'declined'")
        ->and($options['declined']->getAttribute('@click'))->toContain('requestSubmit()');
});

test('the current priority and work status show a checkmark and do not resubmit', function (): void {
    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Advisor));
    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = statusControlsConcern($repairOrder, RepairOrderConcernDisposition::Approved);

    $xpath = statusControlsXPath($this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent());
    $scope = '//*[@id="concern-'.$concern->id.'"]';
    $currentPriority = $xpath->query($scope.'//*[@data-priority-option="must_do"]')->item(0);
    $otherPriority = $xpath->query($scope.'//*[@data-priority-option="should_do"]')->item(0);
    $currentWork = $xpath->query($scope.'//*[@data-production-option="waiting_parts"]')->item(0);
    $otherWork = $xpath->query($scope.'//*[@data-production-option="in_progress"]')->item(0);
    $workForm = $xpath->query('ancestor::form', $currentWork)->item(0);

    expect($currentPriority->getAttribute('aria-current'))->toBe('true')
        ->and($currentPriority->hasAttribute('@click'))->toBeFalse()
        ->and($otherPriority->getAttribute('@click'))->toContain("recommendation_posture.value = 'should_do'")
        ->and($currentWork->getAttribute('aria-current'))->toBe('true')
        ->and($currentWork->hasAttribute('@click'))->toBeFalse()
        ->and($otherWork->getAttribute('@click'))->toContain("production_status.value = 'in_progress'")
        ->and($workForm->getAttribute('action'))->toBe(route('operations.repair-orders.concerns.production-status', [$repairOrder, $concern]))
        ->and(trim($xpath->query($scope.'//*[@data-production-option="pending"]')->item(0)->textContent))->toBe('Not started');
});

test('a closed repair order shows the decision badge instead of the dropdowns', function (): void {
    $this->actingAs(actingAsLearnCurrentStaff(ArkRole::Advisor));
    $repairOrder = repairOrderForEstimateWorkspace();
    $concern = statusControlsConcern($repairOrder, RepairOrderConcernDisposition::Recommended);
    $repairOrder->forceFill(['status' => RepairOrderStatus::Closed])->saveQuietly();

    $xpath = statusControlsXPath($this->get(route('operations.repair-orders.show', $repairOrder))->assertOk()->getContent());
    $badge = $xpath->query('//*[@id="concern-authorization-'.$concern->id.'"]//*[@data-concern-decision]')->item(0);

    expect($badge)->toBeInstanceOf(DOMElement::class)
        ->and(trim($badge->textContent))->toBe('Pending Approval')
        ->and($xpath->query('//*[@data-concern-decision-choices]')->length)->toBe(0);
});

test('priorities use red, yellow, green, and azure without touching decision or work status colors', function (): void {
    expect(RecommendationPresentation::priority(RecommendationPriority::MustDo)->tablerColor())->toBe('red')
        ->and(RecommendationPresentation::priority(RecommendationPriority::ShouldDo)->tablerColor())->toBe('yellow')
        ->and(RecommendationPresentation::priority(RecommendationPriority::CouldDo)->tablerColor())->toBe('green')
        ->and(RecommendationPresentation::informationOnly()->tablerColor())->toBe('azure')
        ->and(RecommendationPriority::ShouldDo->accentColor())->toBe('#a16207')
        ->and(RecommendationPriority::CouldDo->accentColor())->toBe('#15803d')
        ->and(array_map(fn (RepairOrderConcernDisposition $d): string => $d->tablerColor(), RepairOrderConcernDisposition::cases()))
        ->toBe(['secondary', 'blue', 'green', 'yellow', 'red'])
        ->and(array_map(fn (ScopeProductionStatus $s): string => $s->tablerColor(), ScopeProductionStatus::cases()))
        ->toBe(['secondary', 'azure', 'yellow', 'green']);
});
