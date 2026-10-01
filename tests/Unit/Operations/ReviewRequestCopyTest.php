<?php

use App\Ark\Operations\Messaging\ReviewRequestCopy;
use App\Ark\Operations\Settings\ShopSettings;

test('review request url reads Core google_reviews_url', function (): void {
    ShopSettings::current()->forceFill([
        'google_reviews_url' => 'https://reviews.example.test/core',
    ])->save();

    expect(ReviewRequestCopy::reviewUrl())->toBe('https://reviews.example.test/core');
});

test('review request url is empty when google_reviews_url is unset', function (): void {
    ShopSettings::current()->forceFill([
        'google_reviews_url' => null,
    ])->save();

    expect(ReviewRequestCopy::reviewUrl())->toBe('');
});
