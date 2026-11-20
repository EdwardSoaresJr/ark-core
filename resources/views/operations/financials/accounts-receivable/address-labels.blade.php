<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Address Labels</title>
    <style>
        @page { size: Letter; margin: 0.5in 0.19in; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; font-size: 11px; color: #111; }
        .sheet { width: 8.12in; display: grid; grid-template-columns: repeat(3, 2.625in); grid-auto-rows: 1in; column-gap: 0.125in; }
        .label { padding: 0.12in 0.14in; box-sizing: border-box; overflow: hidden; }
        .name { font-weight: 700; margin-bottom: 2px; }
        .missing { padding: 24px; color: #b91c1c; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
@if (count($missing_address) > 0)
    <div class="missing no-print">Missing address for: {{ implode(', ', $missing_address) }}</div>
@endif
<div class="sheet">
    @foreach ($labels as $label)
        <div class="label">
            <div class="name">{{ $label['name'] }}</div>
            @foreach ($label['lines'] as $line)
                <div>{{ $line }}</div>
            @endforeach
        </div>
    @endforeach
</div>
</body>
</html>
