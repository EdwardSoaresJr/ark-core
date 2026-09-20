@php
    /** @var \App\Ark\Operations\Printing\PartsLabelPrintContext $partsLabelPrintContext */
    $lw = isset($labelWidthMm) ? (float) $labelWidthMm : 62.0;
    $lh = isset($labelHeightMm) ? (float) $labelHeightMm : 38.1;
    $ctx = $partsLabelPrintContext;
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
            margin: 0;
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.15;
            color: #111;
        }
        .tag {
            display: table;
            table-layout: fixed;
            width: {{ number_format($lw, 2, '.', '') }}mm;
            height: {{ number_format($lh, 2, '.', '') }}mm;
            padding: 1mm;
            box-sizing: border-box;
            overflow: hidden;
        }
        .tag-mid {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            width: 100%;
            height: 100%;
            padding: 0;
            box-sizing: border-box;
            line-height: 0;
        }
        .tag-stack {
            display: inline-block;
            width: 100%;
            box-sizing: border-box;
            text-align: center;
            vertical-align: middle;
            line-height: normal;
            margin-top: -6mm;
            transform: translateY(-2mm);
        }
        .tag-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.55mm;
            width: 100%;
            max-width: 100%;
            height: auto;
            max-height: 100%;
            box-sizing: border-box;
            text-align: center;
        }
        .pl-row {
            margin: 0;
            flex-shrink: 0;
            max-width: 100%;
            width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .pl-ro {
            font-size: 15px;
            font-weight: 700;
            line-height: 1.1;
        }
        .pl-ymm {
            font-size: 11px;
            font-weight: 400;
            line-height: 1.1;
            color: #111;
        }
        .pl-part {
            font-size: 13px;
            font-weight: 700;
            line-height: 1.1;
            letter-spacing: 0.02em;
        }
        .pl-desc {
            font-size: 11px;
            font-weight: 400;
            line-height: 1.15;
            white-space: normal;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .pl-qty {
            font-size: 12px;
            font-weight: 700;
            line-height: 1.1;
        }
    </style>
</head>
<body>
<div class="tag">
<div class="tag-mid">
<div class="tag-stack">
<div class="tag-inner">
    <div class="pl-row pl-ro">{{ $ctx->roNumberLine }}</div>
    <div class="pl-row pl-ymm">{{ $ctx->vehicleLine }}</div>
    <div class="pl-row pl-part">{{ $ctx->partNumberLine }}</div>
    <div class="pl-row pl-desc">{{ $ctx->descriptionLine }}</div>
    <div class="pl-row pl-qty">{{ $ctx->quantityLine }}</div>
</div>
</div>
</div>
</div>
</body>
</html>
