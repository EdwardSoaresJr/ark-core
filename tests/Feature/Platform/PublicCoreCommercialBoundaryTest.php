<?php

test('public core application code does not call twilio or the legacy platform texting client', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
    $packages = array_keys(array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []));

    foreach ($packages as $package) {
        expect(strtolower($package))->not->toMatch('/twilio|squareup|square\/square|partstech/');
    }

    $forbidden = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app')));
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());
        foreach (['api.twilio.com', 'PlatformOutboundSmsTransport', 'ArkTextingClient'] as $needle) {
            if (str_contains($contents, $needle)) {
                $forbidden[] = str_replace(base_path().'/', '', $file->getPathname()).' contains '.$needle;
            }
        }
    }

    expect($forbidden)->toBe([]);
});
