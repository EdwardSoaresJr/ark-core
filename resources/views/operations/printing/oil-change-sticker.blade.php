@php
    /** @var \App\Ark\Operations\Printing\OilChangeStickerPrintContext $oilStickerPrintContext */
    $lw = isset($labelWidthMm) ? (float) $labelWidthMm : 62.0;
    $lh = isset($labelHeightMm) ? (float) $labelHeightMm : 38.1;
    $oilStickerBlocks = $oilStickerBlocks ?? [];
    $oilStickerDueCombinedSlot = $oilStickerDueCombinedSlot ?? 'printed';
    $oilStickerMergeVehicleMileage = $oilStickerMergeVehicleMileage ?? false;
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
            /* Match key tag: Chromium/Browsershot translateY + ARK-SMS margin baseline. */
            margin-top: -3.5mm;
            transform: translateY(-2mm);
        }
        .tag-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.75mm;
            width: 100%;
            max-width: 100%;
            height: auto;
            max-height: 100%;
            box-sizing: border-box;
            text-align: center;
        }
        .tag-inner > .kt-row {
            margin: 0;
            flex-shrink: 0;
            max-width: 100%;
        }
        .tag-inner > .kt-oil-mi {
            margin-top: 0.1mm !important;
            margin-bottom: 0.15mm !important;
        }
        .tag-inner > .kt-oil-date {
            margin-top: 0.55mm !important;
            padding-top: 0.15mm;
        }
        .tag-inner > .kt-row:not(.kt-qr) {
            width: 100%;
        }
    </style>
</head>
<body>
<div class="tag">
<div class="tag-mid">
<div class="tag-stack">
<div class="tag-inner">
@foreach ($oilStickerBlocks as $blockType)
    @include('operations.printing.blocks.'.$blockType, [
        'ctx' => $oilStickerPrintContext,
        'size' => 'md',
        'dueCombinedSlot' => $oilStickerDueCombinedSlot,
        'mergeVehicleMileage' => $oilStickerMergeVehicleMileage,
    ])
@endforeach
</div>
</div>
</div>
</div>
</body>
</html>
