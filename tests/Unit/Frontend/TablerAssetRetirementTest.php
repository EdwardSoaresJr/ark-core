<?php

test('staff app.js no longer imports Preline', function () {
    $appJs = file_get_contents(resource_path('js/app.js'));

    expect($appJs)->not->toContain("import('preline')")
        ->and($appJs)->not->toMatch("/import\\(['\"]preline['\"]\\)/")
        ->and($appJs)->toContain('TABLER_LEGACY_ASSET_RETIREMENT');
});

test('tailwind content scan does not include Preline sources', function () {
    $config = file_get_contents(base_path('tailwind.config.js'));

    expect($config)->not->toContain("node_modules/preline/dist")
        ->and($config)->toContain('TABLER_LEGACY_ASSET_RETIREMENT');
});

test('unused Preline disclosure component is removed', function () {
    expect(file_exists(resource_path('views/components/ark-disclosure.blade.php')))->toBeFalse();
});
