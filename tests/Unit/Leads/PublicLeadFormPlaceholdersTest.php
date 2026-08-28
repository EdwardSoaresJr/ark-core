<?php

use App\Ark\Operations\Leads\Public\PublicLeadFormPlaceholders;

test('public lead form placeholders include the full wink catalog', function () {
    $sets = PublicLeadFormPlaceholders::sets();

    expect($sets)->toHaveCount(6)
        ->and(collect($sets)->pluck('first_name')->all())->toBe([
            'Tommy',
            'Dade',
            'Kate',
            'Peter',
            'Ferris',
            'Thomas',
        ])
        ->and(collect($sets)->pluck('last_name')->all())->toBe([
            'Tutone',
            'Murphy',
            'Libby',
            'Venkman',
            'Bueller',
            'Anderson',
        ])
        ->and(collect($sets)->pluck('email')->all())->toBe([
            'jenny@example.com',
            'crashoverride@example.com',
            'acidburn@example.com',
            'venkman@example.com',
            'ferris@example.com',
            'neo@example.com',
        ])
        ->and(collect($sets)->pluck('phone')->all())->toBe([
            '555-867-5309',
            '555-555-4202',
            '555-555-4202',
            '555-555-2368',
            '555-555-2383',
            '555-555-0690',
        ])
        ->and(collect($sets)->every(fn (array $set) => str_starts_with($set['phone'], '555-')))->toBeTrue();
});

test('random placeholder set is one of the catalog entries', function () {
    $set = PublicLeadFormPlaceholders::random();

    expect(PublicLeadFormPlaceholders::sets())->toContain($set);
});
