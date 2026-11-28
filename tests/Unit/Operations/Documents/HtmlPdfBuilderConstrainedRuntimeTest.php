<?php

use App\Ark\Operations\Documents\HtmlPdfBuilder;
use App\Ark\Operations\Documents\PdfRuntimePaths;

it('falls back to native PDF when Chromium readiness reports process spawning unavailable', function () {
    config([
        'services.pdf.node_binary' => '',
        'services.pdf.chrome_path' => '',
    ]);

    $bytes = app(HtmlPdfBuilder::class)->toPdfBytes(
        '<html><body><h1>Fidelity Auto — 東京</h1><p>Brake service $150.00</p></body></html>'
    );

    expect(str_starts_with($bytes, '%PDF'))->toBeTrue()
        ->and(strlen($bytes))->toBeGreaterThan(200);
});

it('exposes process spawning availability for constrained hosts', function () {
    expect(PdfRuntimePaths::processSpawningAvailable())->toBeTrue();
});
