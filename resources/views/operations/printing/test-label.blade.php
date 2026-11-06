@php
    $lw = isset($labelWidthMm) ? (float) $labelWidthMm : 62.0;
    $lh = isset($labelHeightMm) ? (float) $labelHeightMm : 38.1;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        @page {
            size: {{ number_format($lw, 2, '.', '') }}mm {{ number_format($lh, 2, '.', '') }}mm;
            margin: 0;
        }
        html, body {
            width: {{ number_format($lw, 2, '.', '') }}mm;
            height: {{ number_format($lh, 2, '.', '') }}mm;
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            color: #111;
        }
        .label {
            width: 100%;
            height: 100%;
            padding: 1.5mm;
            border: 0.4mm solid #111;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 0.8mm;
        }
        .title { font-size: 12pt; font-weight: 700; }
        .shop { font-size: 9pt; }
        .meta { font-size: 7pt; }
    </style>
</head>
<body>
<div class="label">
    <div class="title">ARK printing test</div>
    <div class="shop">{{ $shopName }}</div>
    <div class="meta">{{ $printedAt }} · Not a customer document</div>
</div>
</body>
</html>
