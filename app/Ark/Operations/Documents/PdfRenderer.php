<?php

namespace App\Ark\Operations\Documents;

interface PdfRenderer
{
    public function renderEstimate(EstimateDocument $document): string;
}
