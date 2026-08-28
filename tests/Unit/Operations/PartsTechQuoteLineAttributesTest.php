<?php

use App\Ark\Operations\Parts\PartsTechQuoteLineAttributes;

test('parts tech quote line attributes extract position from product attributes', function () {
    $item = [
        'builtItem' => [
            'product' => [
                'attributes' => [
                    ['name' => 'Material', 'value' => ['Ceramic']],
                    ['name' => 'Position', 'value' => ['Front']],
                ],
            ],
        ],
    ];

    expect(PartsTechQuoteLineAttributes::positionLabel($item))->toBe('Front');
});

test('parts tech quote line attributes prepend position to generic brake descriptions', function () {
    expect(PartsTechQuoteLineAttributes::labeledDescription(
        'BrakeBest Select Ceramic Disc Brake Pad Set',
        'Rear',
    ))->toBe('Rear — BrakeBest Select Ceramic Disc Brake Pad Set')
        ->and(PartsTechQuoteLineAttributes::labeledDescription(
            'Front brake pads',
            'Front',
        ))->toBe('Front brake pads');
});
