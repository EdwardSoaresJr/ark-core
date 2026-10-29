<?php

use App\Ark\Operations\Settings\ShopSettings;

test('shop settings skip columns this database does not have', function () {
    $settings = ShopSettings::current();
    $email = $settings->email;

    expect($settings->persistTrusted(['not_a_shop_column' => 'should-not-save']))->toBeTrue()
        ->and($settings->fresh()->email)->toBe($email);

    $settings->persistTrusted([
        'email' => 'desk@example.test',
        'shop_name' => 'Example Shop',
        'postmark_reply_to' => '',
        'postmark_reply_to_name' => '',
    ]);
    $settings = $settings->fresh();

    expect($settings->customerReplyToAddress())->toBe('desk@example.test')
        ->and($settings->customerReplyToName())->toBe('Example Shop');
});
