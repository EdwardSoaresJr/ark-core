<?php

use App\Ark\Operations\RepairOrders\RepairOrderMention;
use Tests\TestCase;


test('repair order mention html links same-customer shop numbers', function () {
    $html = RepairOrderMention::html('Comeback from @RO1677 today.', [
        1677 => 'https://shop.test/app/repair-orders/1677',
    ]);

    expect($html)
        ->toContain('href="https://shop.test/app/repair-orders/1677"')
        ->toContain('@RO1677')
        ->toContain('ops-page-link');
});

test('repair order mention html is case insensitive and escapes surrounding text', function () {
    $html = RepairOrderMention::html('<script>@ro42</script>', [
        42 => '/app/repair-orders/42',
    ]);

    expect($html)
        ->toContain('&lt;script&gt;')
        ->toContain('href="/app/repair-orders/42"')
        ->not->toContain('<script>');
});

test('repair order mention html does not link numbers outside the allowed map', function () {
    $html = RepairOrderMention::html('See @RO99 and @RO100', [
        100 => '/app/repair-orders/100',
    ]);

    expect($html)
        ->toContain('@RO99')
        ->not->toContain('href="/app/repair-orders/99"')
        ->toContain('href="/app/repair-orders/100"');
});

test('repair order mention extracts shop numbers from tokens', function () {
    expect(RepairOrderMention::numbersIn('Follow-up @RO12 and @RO#34'))
        ->toBe([12, 34]);
});
