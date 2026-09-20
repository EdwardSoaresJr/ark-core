<?php

namespace Tests\Support;

use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\Documents\PdfRenderer;

final class TestingFakePdfRenderer implements PdfRenderer
{
    public function renderEstimate(EstimateDocument $document): string
    {
        return "%PDF-1.4\n%ARK-TEST-FAKE\n";
    }
}
