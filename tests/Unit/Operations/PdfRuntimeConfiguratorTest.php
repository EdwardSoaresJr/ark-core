<?php

use App\Ark\Operations\Documents\PdfRuntimeConfigurator;
use App\Ark\Operations\Documents\PdfRuntimePaths;

it('re-resolves chrome when cached path is stale', function (): void {
    config([
        'services.pdf.chrome_path' => '/tmp/arksms-missing-chrome-'.uniqid('', true),
        'services.pdf.node_binary' => config('services.pdf.node_binary'),
        'services.pdf.npm_binary' => config('services.pdf.npm_binary'),
    ]);

    PdfRuntimeConfigurator::apply();

    $chrome = config('services.pdf.chrome_path');

    if ($chrome === null) {
        expect(PdfRuntimePaths::resolveChromePath())->toBeNull();

        return;
    }

    expect($chrome)->toBeString()->and(is_executable($chrome))->toBeTrue();
});

it('does not crash application boot when discovery throws', function (): void {
    config(['services.pdf.node_binary' => null]);

    PdfRuntimeConfigurator::apply();

    expect(true)->toBeTrue();
});

it('leaves sandbox enabled outside linux containers', function (): void {
    config(['services.pdf.no_sandbox' => false]);

    PdfRuntimeConfigurator::apply();

    if (PHP_OS_FAMILY === 'Linux' && (is_file('/.dockerenv') || is_file('/run/.containerenv'))) {
        expect(config('services.pdf.no_sandbox'))->toBeTrue();

        return;
    }

    expect(filter_var(config('services.pdf.no_sandbox'), FILTER_VALIDATE_BOOLEAN))->toBeFalse();
});
