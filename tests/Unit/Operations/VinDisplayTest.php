<?php

use App\Ark\Operations\Vehicles\VinDisplay;

test('vin display splits prefix and last eight suffix', function () {
    $parts = VinDisplay::parts('1HGCM82633A004352');

    expect($parts)->toMatchArray([
        'vin' => '1HGCM82633A004352',
        'prefix' => '1HGCM8263',
        'suffix' => '3A004352',
        'suffix_start' => 9,
    ])
        ->and($parts['chars'])->toHaveCount(17);
});

test('vin display treats short vins as all suffix', function () {
    $parts = VinDisplay::parts('abc');

    expect($parts)->toMatchArray([
        'vin' => 'ABC',
        'prefix' => '',
        'suffix' => 'ABC',
        'suffix_start' => 0,
    ]);
});

test('vin display returns null for blank vin', function () {
    expect(VinDisplay::parts(null))->toBeNull()
        ->and(VinDisplay::parts('   '))->toBeNull();
});

test('vin display phonetic spelling uses nato alphabet for phone readback', function () {
    expect(VinDisplay::phonetic('3'))->toBe('Three')
        ->and(VinDisplay::phonetic('A'))->toBe('Alpha')
        ->and(VinDisplay::phonetic('H'))->toBe('Hotel');
});

test('vin display uses standard wmi vds and vis sections', function () {
    $sections = VinDisplay::sections('1HGCM82633A004352');

    expect($sections)->toHaveCount(3)
        ->and($sections[0]['label'])->toBe('WMI')
        ->and($sections[0]['chars'])->toBe(['1', 'H', 'G'])
        ->and($sections[1]['label'])->toBe('VDS')
        ->and($sections[1]['chars'])->toBe(['C', 'M', '8', '2', '6', '3'])
        ->and($sections[2]['label'])->toBe('Serial')
        ->and($sections[2]['chars'])->toBe(['3', 'A', '0', '0', '4', '3', '5', '2'])
        ->and($sections[2]['is_serial'])->toBeTrue();
});

test('vin display parts include phonetic chars with suffix still split at last eight', function () {
    $parts = VinDisplay::parts('1HGCM82633A004352');

    expect($parts['phonetic_chars'])->toBe([
        'One', 'Hotel', 'Golf', 'Charlie', 'Mike', 'Eight', 'Two', 'Six', 'Three',
        'Three', 'Alpha', 'Zero', 'Zero', 'Four', 'Three', 'Five', 'Two',
    ])
        ->and($parts['phonetic_chars'][9])->toBe('Three')
        ->and($parts['phonetic_chars'][10])->toBe('Alpha');
});
