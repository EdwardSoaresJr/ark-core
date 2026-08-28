<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Vehicle Inspection — RO #{{ $report['identity']['repair_order_id'] ?? '' }}</title>
    <style>
        @page {
            size: Letter;
            margin: 0.45in 0.5in 0.6in;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            color: #0f172a;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.45;
        }

        h1, h2, h3, p, ul, ol, figure { margin: 0; }

        .report { width: 100%; }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #cbd5e1;
        }

        .shop-name {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }

        .shop-meta {
            margin-top: 4px;
            color: #475569;
            font-size: 10px;
        }

        .logo {
            max-height: 42px;
            max-width: 140px;
            object-fit: contain;
        }

        .identity {
            margin-top: 14px;
        }

        .identity h1 {
            font-size: 18px;
            font-weight: 800;
        }

        .identity-sub {
            margin-top: 4px;
            color: #475569;
            font-size: 11px;
        }

        .chips {
            margin-top: 8px;
            color: #334155;
            font-size: 10px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            margin-top: 14px;
            margin-bottom: 12px;
        }

        .summary-card {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px;
            background: #f8fafc;
        }

        .summary-card.attention { border-color: #f59e0b; background: #fffbeb; }
        .summary-card.monitor { border-color: #38bdf8; background: #f0f9ff; }
        .summary-card.ok { border-color: #34d399; background: #ecfdf5; }

        .summary-num {
            font-size: 18px;
            font-weight: 800;
        }

        .summary-label {
            margin-top: 2px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
        }

        .summary-split {
            margin-top: 3px;
            font-size: 9px;
            color: #92400e;
        }

        .section-head {
            margin-top: 14px;
            margin-bottom: 6px;
            padding: 6px 8px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #334155;
            break-after: avoid;
            page-break-after: avoid;
        }

        .section-head.attention { background: #fffbeb; border-color: #fde68a; color: #92400e; }
        .section-head.monitor { background: #f0f9ff; border-color: #bae6fd; color: #075985; }
        .section-head.ok { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }

        .finding-block {
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .finding-category {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
        }

        .finding-title {
            margin-top: 2px;
            font-size: 13px;
            font-weight: 800;
        }

        .finding-badge {
            display: inline-block;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 9px;
            font-weight: 700;
            background: #f8fafc;
        }

        .measurement-box {
            margin-top: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #f8fafc;
            padding: 6px 8px;
        }

        .measurement-title {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
        }

        .measurement-lines {
            margin-top: 4px;
            font-size: 11px;
            font-weight: 700;
        }

        .finding-observation,
        .finding-note {
            margin-top: 6px;
            color: #334155;
            font-size: 11px;
        }

        .finding-evidence {
            margin-top: 8px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .evidence-photo img {
            width: 100%;
            max-height: 140px;
            object-fit: cover;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
        }

        .evidence-video {
            border: 1px dashed #94a3b8;
            border-radius: 4px;
            padding: 8px;
            background: #f8fafc;
        }

        .evidence-video-label {
            font-size: 10px;
            font-weight: 700;
            color: #0f172a;
        }

        .evidence-video-link {
            margin-top: 4px;
            font-size: 9px;
            color: #334155;
            word-break: break-all;
        }

        .evidence-video-link a { color: #0369a1; }

        .ok-list,
        .na-list {
            margin-top: 6px;
            padding-left: 16px;
            color: #334155;
        }

        .ok-list li,
        .na-list li {
            margin-bottom: 4px;
        }

        .footer {
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid #cbd5e1;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-end;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .footer-meta {
            color: #64748b;
            font-size: 9px;
        }

        .footer-qr {
            text-align: center;
        }

        .footer-qr img {
            width: 72px;
            height: 72px;
        }

        .footer-qr-label {
            margin-top: 4px;
            font-size: 8px;
            color: #64748b;
            max-width: 140px;
            word-break: break-all;
        }

        .screen-print-bar {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin: 0 0 12px;
        }

        .screen-print-bar button {
            border: 1px solid #cbd5e1;
            background: #0f172a;
            color: #fff;
            font: inherit;
            font-size: 12px;
            font-weight: 700;
            padding: 8px 12px;
            cursor: pointer;
        }

        @media print {
            a { color: inherit; text-decoration: none; }
            .screen-print-bar { display: none !important; }
        }
    </style>
</head>
<body>
@php
    $shop = $report['shop'] ?? [];
    $vehicle = $report['vehicle'] ?? [];
    $identity = $report['identity'] ?? [];
    $summary = $report['summary'] ?? [];
    $mode = $mode ?? ($report['mode'] ?? 'simple');
    $isDetailed = $mode === 'detailed';
    $liveUrl = $report['live_report_url'] ?? null;
    $qr = $report['qr_data_uri'] ?? null;
    $headlineAttention = (int) ($summary['headline_needs_attention'] ?? 0);
    $failedCount = (int) ($summary['failed_count'] ?? 0);
    $needsAttentionOnly = (int) ($summary['needs_attention_count'] ?? 0);
@endphp

<div class="screen-print-bar">
    <button type="button" onclick="window.print()">Print</button>
</div>

<div class="report">
    <header class="header">
        <div>
            <p class="shop-name">{{ $shop['name'] ?? 'Shop' }}</p>
            <p class="shop-meta">
                @if (filled($shop['phone'] ?? null)){{ $shop['phone'] }}@endif
                @if (filled($shop['address_line_1'] ?? null))
                    · {{ $shop['address_line_1'] }}
                    @if (filled($shop['city'] ?? null)), {{ $shop['city'] }}@endif
                    @if (filled($shop['state'] ?? null)) {{ $shop['state'] }}@endif
                @endif
            </p>
        </div>
        @if (filled($shop['logo_data_uri'] ?? $shop['logo_url'] ?? null))
            <img class="logo" src="{{ $shop['logo_data_uri'] ?? $shop['logo_url'] }}" alt="">
        @endif
    </header>

    <div class="identity">
        <h1>{{ $identity['title'] ?? 'Vehicle Inspection' }}</h1>
        <p class="identity-sub">{{ $vehicle['display_name'] ?? 'Vehicle' }}</p>
        <p class="chips">
            RO #{{ $identity['repair_order_id'] ?? '' }}
            @if (filled($identity['template_name'] ?? null))
                · {{ $identity['template_name'] }}
            @endif
            @if (filled($vehicle['mileage_in'] ?? null))
                · {{ number_format((int) $vehicle['mileage_in']) }} mi
            @endif
            @if (filled($identity['inspected_at_label'] ?? null))
                · {{ $identity['inspected_at_label'] }}
            @endif
            @if (filled($identity['technician_name'] ?? null))
                · Tech {{ $identity['technician_name'] }}
            @endif
            · {{ $isDetailed ? 'Detailed' : 'Simple' }}
        </p>
    </div>

    <div class="summary">
        <div class="summary-card attention">
            <div class="summary-num">{{ $headlineAttention }}</div>
            <div class="summary-label">Need Attention</div>
            @if ($failedCount > 0)
                <div class="summary-split">{{ $needsAttentionOnly }} Needs Attention · {{ $failedCount }} Failed</div>
            @endif
        </div>
        <div class="summary-card monitor">
            <div class="summary-num">{{ (int) ($summary['monitor_count'] ?? 0) }}</div>
            <div class="summary-label">Monitor</div>
        </div>
        <div class="summary-card ok">
            <div class="summary-num">{{ (int) ($summary['ok_count'] ?? 0) }}</div>
            <div class="summary-label">Checked OK</div>
        </div>
        <div class="summary-card">
            <div class="summary-num">{{ (int) ($summary['na_count'] ?? 0) }}</div>
            <div class="summary-label">N/A</div>
        </div>
    </div>

    @if (! ($report['ready'] ?? false))
        <p>No inspection findings are on this report yet.</p>
    @elseif ($isDetailed)
        @foreach (($report['categories'] ?? []) as $category)
            <div class="section-head">{{ $category['name'] }}</div>
            @foreach (($category['points'] ?? []) as $point)
                @include('portal.partials._inspection-report-finding', [
                    'point' => $point,
                    'variant' => 'print',
                    'liveReportUrl' => $liveUrl,
                    'showVideosPlayable' => false,
                ])
            @endforeach
        @endforeach
    @else
        @if (($report['attention_findings'] ?? []) !== [])
            <div class="section-head attention">Needs Attention</div>
            @foreach ($report['attention_findings'] as $point)
                @include('portal.partials._inspection-report-finding', [
                    'point' => $point,
                    'variant' => 'print',
                    'liveReportUrl' => $liveUrl,
                    'showVideosPlayable' => false,
                ])
            @endforeach
        @endif

        @if (($report['monitor_findings'] ?? []) !== [])
            <div class="section-head monitor">Monitor</div>
            @foreach ($report['monitor_findings'] as $point)
                @include('portal.partials._inspection-report-finding', [
                    'point' => $point,
                    'variant' => 'print',
                    'liveReportUrl' => $liveUrl,
                    'showVideosPlayable' => false,
                ])
            @endforeach
        @endif

        @php
            $ok = $report['ok_condensed'] ?? ['count' => 0, 'by_category' => []];
        @endphp
        @if (($ok['count'] ?? 0) > 0)
            <div class="section-head ok">Checked &amp; OK ({{ (int) $ok['count'] }})</div>
            <p style="margin-top:6px;color:#475569;">The rest of the vehicle was checked. These points were in good condition.</p>
            <ul class="ok-list">
                @foreach (($ok['by_category'] ?? []) as $group)
                    <li>
                        <strong>{{ $group['category'] }}</strong>
                        ({{ (int) $group['count'] }}) —
                        {{ implode(', ', $group['labels'] ?? []) }}
                    </li>
                @endforeach
            </ul>
        @endif

        @if (($report['na_findings'] ?? []) !== [])
            <div class="section-head">N/A / Not performed ({{ count($report['na_findings']) }})</div>
            <ul class="na-list">
                @foreach ($report['na_findings'] as $point)
                    <li>
                        <strong>{{ $point['label'] }}</strong>
                        @if (filled($point['note'] ?? null)) — {{ $point['note'] }}@endif
                    </li>
                @endforeach
            </ul>
        @endif
    @endif

    <footer class="footer">
        <div class="footer-meta">
            <div>{{ $shop['name'] ?? 'Shop' }} · Vehicle Inspection · RO #{{ $identity['repair_order_id'] ?? '' }}</div>
            <div style="margin-top:4px;">Ask us about anything on this report.</div>
            @if (filled($shop['phone'] ?? null))
                <div style="margin-top:2px;">{{ $shop['phone'] }}</div>
            @endif
        </div>
        @if (filled($qr) && filled($liveUrl))
            <div class="footer-qr">
                <img src="{{ $qr }}" alt="Live report QR">
                <div class="footer-qr-label">Live report<br>{{ $liveUrl }}</div>
            </div>
        @endif
    </footer>
</div>
</body>
</html>
