<?php

use App\Ark\Operations\Customers\Customer;

test('customer email is stored trimmed lowercase on create and update', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Rosa',
        'last_name' => 'Diaz',
        'email' => '  Rosa.Diaz@Example.TEST ',
        'customer_type' => 'Retail',
    ]);

    expect($customer->email)->toBe('rosa.diaz@example.test')
        ->and($customer->fresh()->email)->toBe('rosa.diaz@example.test');

    $customer->update(['email' => 'ROSA@EXAMPLE.TEST']);

    expect($customer->fresh()->email)->toBe('rosa@example.test');

    $customer->update(['email' => '   ']);

    expect($customer->fresh()->email)->toBeNull();
});
