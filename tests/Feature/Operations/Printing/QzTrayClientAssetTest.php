<?php

test('QZ Tray client script is present on the path the browser loads', function (): void {
    $canonical = public_path('vendor/qz/qz-tray.js');
    $legacy = public_path('js/ark/qz-tray.js');

    expect(is_file($canonical))->toBeTrue()
        ->and(filesize($canonical))->toBeGreaterThan(10_000)
        ->and(is_file($legacy))->toBeTrue()
        ->and(filesize($legacy))->toBeGreaterThan(10_000);
});

test('print helpers load QZ Tray from public/vendor/qz', function (): void {
    $helpers = (string) file_get_contents(resource_path('views/components/operations/print-helpers.blade.php'));

    expect($helpers)->toContain("asset('vendor/qz/qz-tray.js')");
});

test('dockerignore excludes composer vendor without dropping public QZ assets', function (): void {
    $ignore = (string) file_get_contents(base_path('.dockerignore'));
    $lines = preg_split('/\R/', $ignore) ?: [];

    expect($lines)->not->toContain('vendor')
        ->and($lines)->toContain('/vendor')
        ->and($lines)->toContain('!public/vendor')
        ->and($ignore)->not->toMatch('/^vendor$/m');
});

test('Dockerfile refuses to finish if the QZ Tray client is missing from the image', function (): void {
    $dockerfile = (string) file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)->toContain('test -f /app/public/js/ark/qz-tray.js')
        ->and($dockerfile)->toContain('test -f /app/public/vendor/qz/qz-tray.js');
});
