<?php

use App\Ark\Platform\Cloud\CloudUrls;

test('stock core does not call Hosted ARK or embed a recovery key', function () {
    expect((string) config('services.ark_platform.base_url'))->toBe('')
        ->and((string) config('services.ark_cloud.base_url'))->toBe('')
        ->and((string) config('services.ark_platform.offline_recovery_public_key'))->toBe('')
        ->and((string) config('services.ark_cloud.offline_recovery_public_key'))->toBe('')
        ->and(CloudUrls::base())->toBe('');
});
