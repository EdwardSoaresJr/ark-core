<?php

use App\Ark\Operations\Intake\IntakeConcernParser;

test('intake concern parser splits customer states by line', function () {
    $parser = new IntakeConcernParser;

    $concerns = $parser->parse("Check engine light on.\nVehicle shaking on highway.\nNeeds oil change.");

    expect($concerns)->toHaveCount(3)
        ->and($concerns[0]['summary'])->toBe('Check engine light on.')
        ->and($concerns[1]['customer_states'])->toContain('shaking')
        ->and($concerns[2]['summary'])->toBe('Needs oil change.');
});

test('intake concern parser parse row keeps multiline text in one concern', function () {
    $parser = new IntakeConcernParser;

    $concern = $parser->parseRow("Check engine light on.\nAlso noticed a smell when idling.");

    expect($concern)
        ->not->toBeNull()
        ->and($concern['summary'])->toBe('Check engine light on.')
        ->and($concern['customer_states'])->toContain('smell when idling');
});

test('intake concern parser strips list prefixes and maintenance label', function () {
    $parser = new IntakeConcernParser;

    $concerns = $parser->parse("- Check engine light on.\n1) Brakes noisy\nMaintenance: Oil change");

    expect($concerns)->toHaveCount(3)
        ->and($concerns[2]['summary'])->toBe('Oil change');
});
