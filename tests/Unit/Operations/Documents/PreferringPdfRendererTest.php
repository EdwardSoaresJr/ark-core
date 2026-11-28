<?php

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\HeadlessChromiumPdfRenderer;
use App\Ark\Operations\Documents\NativePhpPdfRenderer;
use App\Ark\Operations\Documents\PdfRuntimeUnavailable;
use App\Ark\Operations\Documents\PreferringPdfRenderer;

it('falls back to native PHP PDF when Chromium runtime is unavailable', function (): void {
    $document = \Mockery::mock(EstimateDocument::class)->shouldIgnoreMissing();
    $document->shouldReceive('getAttribute')->with('id')->andReturn(42);
    $document->shouldReceive('offsetExists')->andReturn(false);

    $chromium = \Mockery::mock(HeadlessChromiumPdfRenderer::class);
    $chromium->shouldReceive('renderEstimate')
        ->once()
        ->with($document)
        ->andThrow(new PdfRuntimeUnavailable('missing chrome'));

    $native = \Mockery::mock(NativePhpPdfRenderer::class);
    $native->shouldReceive('renderEstimate')
        ->once()
        ->with($document)
        ->andReturn('estimate-documents/demo.pdf');

    $renderer = new PreferringPdfRenderer($chromium, $native);

    expect($renderer->renderEstimate($document))->toBe('estimate-documents/demo.pdf');
});
