<?php

use App\Ark\Operations\LaborGuides\Rte\RteEngineDescriptionFormatter;

test('rte engine description formatter produces advisor readable labels', function (): void {
    $formatter = new RteEngineDescriptionFormatter;

    expect($formatter->format('2367cc 22R 4 CYLINDER'))->toBe('2.4L 22R 4-cyl')
        ->and($formatter->format('2367cc 22RE BOSCH AIR FLOW CTRL'))->toBe('2.4L 22RE')
        ->and($formatter->format('2958cc 3VZE 6 CYLINDER'))->toBe('3L 3VZE 6-cyl')
        ->and($formatter->format('5.7L MULTIPORT FI (HEMI)'))->toBe('5.7L HEMI');
});
