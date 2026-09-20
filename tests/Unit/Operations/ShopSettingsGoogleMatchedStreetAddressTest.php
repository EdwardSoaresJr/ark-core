<?php

use App\Ark\Operations\Settings\ShopSettings;

it('formats street address to match google business profile prominence', function (): void {
    $shop = new ShopSettings([
        'address_line_1' => '100 Main Street',
        'address_line_2' => 'Unit D',
    ]);

    expect($shop->googleMatchedStreetAddress())->toBe('100 Main Street D');
});

it('appends bare suite tokens without a unit prefix', function (): void {
    $shop = new ShopSettings([
        'address_line_1' => '100 Main Street',
        'address_line_2' => 'D',
    ]);

    expect($shop->googleMatchedStreetAddress())->toBe('100 Main Street D');
});

it('returns line one when suite is empty', function (): void {
    $shop = new ShopSettings([
        'address_line_1' => '100 Main Street',
        'address_line_2' => null,
    ]);

    expect($shop->googleMatchedStreetAddress())->toBe('100 Main Street');
});

it('falls back to the google-matched publication street when settings have no street', function (): void {
    $shop = new ShopSettings([
        'address_line_1' => '',
        'address_line_2' => null,
    ]);

    expect($shop->publicationStreetAddress())->toBe('100 Main Street Suite A');
});
