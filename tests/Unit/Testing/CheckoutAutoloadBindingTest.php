<?php

use App\Ark\Runtime\ShopChannel;
use Tests\TestCase;

it('loads application and test classes from this checkout', function () {
    $root = realpath(dirname(__DIR__, 3));
    $testCase = realpath((new ReflectionClass(TestCase::class))->getFileName());
    $shopChannel = realpath((new ReflectionClass(ShopChannel::class))->getFileName());

    expect($root)->toBeString()
        ->and($testCase)->toStartWith($root.DIRECTORY_SEPARATOR)
        ->and($shopChannel)->toStartWith($root.DIRECTORY_SEPARATOR)
        ->and(realpath(base_path()))->toBe($root);
});
