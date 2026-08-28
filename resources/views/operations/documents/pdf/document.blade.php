<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>RO #{{ $snapshot['repair_order']['repair_order_id'] }} {{ $snapshot['pdf_document_label'] ?? 'Estimate' }}</title>
    <style>
        @page {
            size: Letter;
            margin: 0.32in 0.34in 0.58in;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            color: #0f172a;
            font-family: @include('operations.documents.partials._pdf-font-stack');
            font-size: 11px;
            line-height: 1.45;
        }

        .document {
            width: 100%;
        }

        @include('operations.documents.partials._pdf-header-styles')

        @include('operations.documents.partials._pdf-concern-chrome')

        .document-title {
            margin-top: 0.04in;
            color: #0f172a;
            font-size: 20px;
            font-weight: 900;
            letter-spacing: -0.02em;
            line-height: 1.05;
        }

        h1,
        h2,
        h3,
        p {
            margin: 0;
        }

        h1 {
            font-size: 20px;
            line-height: 1.05;
        }

        h2 {
            font-size: 14px;
        }

        h3 {
            font-size: 12px;
        }

        .approval-gate {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.08in;
            padding: 0.07in 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .box {
            border: 1px solid #e2e8f0;
            padding: 0.055in 0.065in;
        }

        .box h3 {
            line-height: 1.25;
        }

        .section {
            margin-top: 0.1in;
        }

        .intake {
            padding: 0.075in 0.1in;
            border-left: 4px solid #475569;
            background: #f8fafc;
        }

        .concern-header-intent {
            color: inherit;
        }

        .concern-header-intent-sep {
            margin: 0 0.03in;
            color: #cbd5e1;
            font-weight: 500;
        }

        .concern-header-decision {
            display: inline-flex;
            align-items: center;
            gap: 0.03in;
            color: #334155;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.02em;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .concern-header-decision-mark {
            font-size: 10px;
            font-weight: 900;
            line-height: 1;
        }

        .concern-header-decision--approved {
            color: #166534;
        }

        .concern-header-decision--approved .concern-header-decision-mark {
            color: #15803d;
        }

        .concern-header-decision--deferred {
            color: #b45309;
        }

        .concern-header-decision--deferred .concern-header-decision-mark {
            color: #d97706;
        }

        .concern-header-decision--declined {
            color: #b91c1c;
        }

        .concern-header-decision--declined .concern-header-decision-mark {
            color: #dc2626;
        }

        .concern-header-decision--recommended {
            color: #1e40af;
        }

        .line-item {
            display: grid;
            grid-template-columns: 0.44in minmax(0, 1fr) 0.35in 0.52in 0.52in 0.46in 0.46in 0.62in;
            gap: 0.05in;
            padding: 0.05in 0.1in 0.05in 0.12in;
            border-bottom: 1px solid #f1f5f9;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .line-item--note {
            display: block;
            width: 100%;
            grid-template-columns: none;
        }

        .line-item--note .line-desc-col--note {
            display: block;
            width: 100%;
            max-width: none;
            color: #334155;
            font-weight: 600;
        }

        .line-note-label {
            color: #64748b;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.04em;
            line-height: 1.35;
            text-transform: uppercase;
            margin-bottom: 0.02in;
        }

        .line-type-col {
            color: #64748b;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.04em;
            line-height: 1.35;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .line-desc-col {
            min-width: 0;
            color: #0f172a;
            font-weight: 750;
            line-height: 1.35;
            overflow-wrap: normal;
            word-break: normal;
        }

        .line-desc-col--note {
            color: #334155;
            font-weight: 600;
        }

        .line-desc-primary {
            display: block;
        }

        .line-desc-procurement {
            display: block;
            margin-top: 0.02in;
            color: #64748b;
            font-size: 9px;
            font-weight: 600;
            line-height: 1.3;
        }

        .line-desc-procurement-sep {
            margin: 0 0.03in;
            color: #cbd5e1;
        }

        /* Repair is a section label inside the concern — never a nested panel. */
        .repair-action-group {
            margin-top: 0.07in;
            border: none;
            border-top: 1px solid #e2e8f0;
            background: transparent;
        }

        .repair-action-group + .repair-action-group {
            margin-top: 0.06in;
        }

        .repair-action-group + .line-item,
        .line-item + .repair-action-group {
            margin-top: 0.04in;
        }

        .repair-action-header {
            padding: 0.06in 0.1in 0.03in 0.12in;
            background: transparent;
            border: none;
        }

        /* What we're selling — same visual weight family as the finding, not washed-out chrome. */
        .repair-action-title {
            margin: 0;
            color: #0f172a;
            font-size: 11.5px;
            font-weight: 800;
            letter-spacing: -0.01em;
            line-height: 1.25;
            text-transform: none;
        }

        .repair-action-lines .line-item {
            padding-left: 0.12in;
            background: transparent;
            border-bottom-color: #f1f5f9;
        }

        .repair-action-lines .line-item:last-child {
            border-bottom: none;
        }

        .line-item--grouped .line-desc-col--grouped {
            grid-column: 1 / 3;
        }

        .line-desc-row {
            display: inline-flex;
            align-items: center;
            gap: 0.06in;
            min-width: 0;
        }

        /* Quiet type labels — not bordered pills inside the concern frame. */
        .line-type-badge {
            display: inline-flex;
            flex-shrink: 0;
            align-items: center;
            padding: 0;
            border: none;
            border-radius: 0;
            background: transparent;
            font-size: 8.5px;
            font-weight: 800;
            letter-spacing: 0.04em;
            line-height: 1.2;
            white-space: nowrap;
            text-transform: uppercase;
        }

        .line-type-badge--labor {
            color: #065f46;
            background: transparent;
            border: none;
        }

        .line-type-badge--part {
            color: #075985;
            background: transparent;
            border: none;
        }

        .line-desc-detail {
            color: #334155;
            font-size: 10px;
            font-weight: 650;
            line-height: 1.35;
        }

        .line-desc-includes {
            margin: 4px 0 0;
            padding-left: 14px;
            color: #64748b;
            font-size: 9px;
            line-height: 1.45;
        }

        .line-col {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
        }

        .line-col--muted {
            color: #64748b;
            font-weight: 600;
        }

        .line-col--total {
            font-size: 11px;
            font-weight: 800;
        }

        .line-head {
            display: grid;
            grid-template-columns: 0.44in minmax(0, 1fr) 0.35in 0.52in 0.52in 0.46in 0.46in 0.62in;
            gap: 0.05in;
            padding: 0.05in 0.1in 0.045in 0.12in;
            background: transparent;
            border-bottom: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            align-items: end;
        }

        .line-head-work {
            grid-column: 1 / 3;
            color: #0f172a;
            font-size: 11.5px;
            font-weight: 800;
            letter-spacing: -0.01em;
            line-height: 1.25;
            text-transform: none;
        }

        .line-head .line-head-work:empty {
            min-height: 0;
        }

        /* When the first repair title owns the column-head row, don't double-stack chrome. */
        .repair-action-group > .line-head {
            margin: 0;
            border-top: none;
            background: transparent;
        }

        .repair-action-group:first-child {
            border-top: none;
            margin-top: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.08in;
        }

        th {
            padding: 0.045in 0.06in;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 9px;
            letter-spacing: 0.06em;
            text-align: left;
            text-transform: uppercase;
        }

        td {
            padding: 0.055in 0.06in;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }

        .line-note {
            color: #334155;
            font-style: italic;
        }

        .concern-subtotal {
            padding: 0.075in 0.11in;
            text-align: right;
            color: #334155;
            font-weight: 700;
        }

        .additional-charges {
            margin-top: 0.16in;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.08in 0 0.04in;
        }

        .additional-charges-header {
            display: flex;
            justify-content: space-between;
            gap: 0.15in;
            color: #475569;
        }

        .additional-charges-title {
            color: #334155;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .additional-charges table {
            margin-top: 0.04in;
        }

        .additional-charges th {
            padding: 0.04in 0.06in;
            border-top: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
        }

        .additional-charges td {
            padding: 0.045in 0.06in;
        }

        .document-footer {
            margin-top: 0.12in;
        }

        .footer-notes {
            margin-bottom: 0.08in;
            padding-bottom: 0.06in;
            border-bottom: 1px solid #e2e8f0;
        }

        .footer-notes--in-summary {
            margin: 0.1in 0 0;
            padding: 0.08in 0 0;
            border-top: 1px solid #cbd5e1;
            border-bottom: 0;
        }

        .footer-eyebrow {
            margin: 0;
            color: #64748b;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .footer-eyebrow--terms {
            margin-top: 0.05in;
        }

        .footer-bullets-compact {
            margin: 0.02in 0 0;
            padding-left: 0.11in;
            color: #475569;
            font-size: 7.5px;
            line-height: 1.22;
            columns: 2;
            column-gap: 0.1in;
        }

        .footer-bullets-compact--pdf {
            columns: 1;
            font-size: 7.5px;
            line-height: 1.35;
            color: #475569;
        }

        .footer-bullets-compact--single {
            columns: 1;
        }

        .footer-bullets-compact li {
            margin-bottom: 0.02in;
            break-inside: avoid;
        }

        .footer-decision-area {
            border: 1px solid #94a3b8;
            padding: 0.09in 0.1in 0.08in;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .footer-decision-heading {
            display: flex;
            align-items: center;
            gap: 0.1in;
            margin: 0 0 0.08in;
            color: #0f172a;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 0.1em;
            line-height: 1;
            text-transform: uppercase;
        }

        .footer-decision-heading::after {
            content: '';
            flex: 1 1 auto;
            height: 1px;
            background: #cbd5e1;
        }

        .footer-decision-body {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) 2.45in;
            gap: 0.14in;
            align-items: start;
        }

        .footer-decision-totals .totals {
            border: 0;
            border-left: 1px solid #cbd5e1;
            background: transparent;
            padding: 0 0 0 0.12in;
        }

        .footer-decision-totals .totals--with-forecast {
            background: transparent;
        }

        /* Integrated into Estimate Summary — not a nested dashboard card. */
        .approval-forecast-pdf {
            margin: 0 0 0.08in;
            padding: 0 0 0.07in;
            border: 0;
            border-bottom: 1px solid #e2e8f0;
            background: transparent;
        }

        .approval-forecast-pdf__rows {
            margin: 0;
        }

        .approval-forecast-pdf__row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 0.1in;
            padding: 0.02in 0;
            font-size: 9.5px;
            line-height: 1.3;
        }

        .approval-forecast-pdf__row--projected {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 0.02in 0.08in;
            align-items: end;
            margin-top: 0.05in;
            padding-top: 0.07in;
            border-top: 1.5px solid #334155;
            font-size: 10px;
        }

        .approval-forecast-pdf__label {
            color: #475569;
            font-weight: 600;
        }

        .approval-forecast-pdf__label--projected {
            color: #334155;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            font-size: 7.5px;
            line-height: 1.25;
            max-width: 1.55in;
        }

        .approval-forecast-pdf__value {
            color: #0f172a;
            font-weight: 700;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .approval-forecast-pdf__value--pending {
            color: #0f172a;
            font-weight: 700;
        }

        .approval-forecast-pdf__value--projected {
            font-size: 15px;
            font-weight: 800;
            letter-spacing: -0.01em;
            line-height: 1;
        }

        .totals-breakdown-heading {
            margin: 0 0 0.03in;
            color: #94a3b8;
            font-size: 7px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            gap: 0.1in;
            padding: 0.015in 0;
            font-size: 9px;
            color: #475569;
        }

        .totals-row strong,
        .totals-row span:last-child {
            color: #0f172a;
            font-variant-numeric: tabular-nums;
        }

        .totals-row.final {
            margin-top: 0.04in;
            padding-top: 0.06in;
            border-top: 1px solid #94a3b8;
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
        }

        .totals-row--quiet-final {
            margin-top: 0.03in;
            padding-top: 0.04in;
            border-top: 1px solid #e2e8f0;
            font-size: 9.5px;
            font-weight: 700;
            color: #334155;
        }

        .totals-row--credit strong {
            color: #334155;
        }

        .footer-waiver-note {
            margin: 0.04in 0 0;
            color: #475569;
            font-size: 9px;
            font-weight: 600;
            line-height: 1.3;
        }

        .footer-payments {
            margin-top: 0.08in;
            padding-top: 0.06in;
            border-top: 1px solid #e2e8f0;
        }

        .footer-payments-heading {
            margin: 0 0 0.04in;
            color: #64748b;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .footer-payments-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .footer-payments-list li {
            display: flex;
            justify-content: space-between;
            gap: 0.08in;
            padding: 0.02in 0;
            font-size: 9px;
            line-height: 1.35;
        }

        .footer-payment-label {
            color: #475569;
        }

        .footer-payment-amount {
            color: #0f172a;
            font-weight: 700;
            white-space: nowrap;
        }

        .closing-authorization {
            margin: 0;
            color: #334155;
            font-size: 8.5px;
            font-weight: 500;
            line-height: 1.4;
        }

        .closing-approval {
            margin-top: 0.08in;
            padding-top: 0.07in;
            border-top: 1px solid #e2e8f0;
        }

        .closing-status-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 0.1in;
        }

        .closing-status-label {
            color: #64748b;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .closing-status-value {
            color: #0f172a;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: -0.01em;
        }

        .closing-signature-row {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(0.85in, 0.85fr);
            gap: 0.12in;
            margin-top: 0.1in;
        }

        .closing-signature-field {
            display: flex;
            flex-direction: row;
            align-items: flex-end;
            gap: 0.06in;
            min-width: 0;
        }

        .closing-signature-label {
            flex: 0 0 auto;
            color: #64748b;
            font-size: 7.5px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            white-space: nowrap;
            line-height: 1;
            padding-bottom: 0.02in;
        }

        .closing-signature-line {
            display: block;
            flex: 1 1 auto;
            min-width: 0.4in;
            border-bottom: 1px solid #64748b;
            height: 0;
            margin: 0;
        }

        .closing-evidence-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.08in 0.14in;
            margin-top: 0.04in;
            color: #334155;
            font-size: 9px;
        }

        .concern-customer-approval {
            border-top: 1px solid #e2e8f0;
            padding: 0.045in 0.1in 0.055in;
            background: #f8fafc;
        }

        .concern-customer-approval-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.1in 0.14in;
        }

        .concern-approval-label {
            color: #64748b;
            font-size: 8px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .concern-approval-option {
            display: inline-flex;
            align-items: center;
            gap: 0.04in;
            color: #334155;
            font-size: 9px;
            font-weight: 700;
        }

        .concern-approval-box {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 0.11in;
            height: 0.11in;
            border: 1px solid #475569;
            background: #fff;
            color: #fff;
            font-size: 8px;
            font-weight: 800;
            line-height: 1;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .concern-approval-box--approve {
            border-color: #15803d;
            background: #15803d;
        }

        .concern-approval-box--defer {
            border-color: #d97706;
            background: #d97706;
        }

        .concern-approval-box--decline {
            border-color: #dc2626;
            background: #dc2626;
        }

        .contact-line {
            margin-top: 0.02in;
        }
    </style>
</head>
<body>
    @php
        $snapshot = app(\App\Ark\Operations\Documents\DocumentPdfPresenter::class)->prepareForCustomer($snapshot);
    @endphp

    <main class="document">
        @include('operations.documents.partials._pdf-shop-header', ['shop' => $snapshot['shop']])

        @include('operations.documents.estimates._operational-identity-band', [
            'snapshot' => $snapshot,
            'variant' => 'pdf',
        ])

        @php
            $pdfVisitReason = trim((string) ($snapshot['intake']['visit_reason'] ?? ''));
        @endphp

        @if ($pdfVisitReason !== '')
            <section class="section">
                <p class="eyebrow">Reason for Visit</p>
                <p style="margin-top: 0.04in; color: #64748b; font-size: 9px; font-weight: 700;">Customer reported:</p>
                <p style="margin-top: 0.04in; white-space: pre-line; color: #334155; font-size: 10px; line-height: 1.45;">{{ $pdfVisitReason }}</p>
            </section>
        @endif

        @if (count($snapshot['concerns']) === 0)
            <section class="section intake">
                <p class="eyebrow">Check In Concern</p>
                <p>{{ $snapshot['intake']['concern_summary'] }}</p>
            </section>
        @endif

        <section class="section">
            @php
                use App\Ark\Operations\RepairOrders\RecommendationIntent;

                $orderedConcerns = RecommendationIntent::sortedSnapshotConcerns($snapshot['concerns'] ?? [])
                    ->filter(function (array $concern): bool {
                        $disposition = \App\Ark\Operations\RepairOrders\RepairOrderConcernDisposition::fromStored(
                            (string) ($concern['disposition'] ?? ''),
                        );

                        return $disposition?->visibleToCustomer() ?? false;
                    })
                    ->values();
            @endphp

            @if ($orderedConcerns->isNotEmpty())
                <p class="eyebrow" style="margin-bottom: 0.08in;">Recommended Work</p>
            @endif

            @foreach ($orderedConcerns as $concern)
                @php
                    $customerStates = trim((string) ($concern['customer_states'] ?? ''));
                    $duplicateCustomerStates = $customerStates !== ''
                        && in_array(mb_strtolower($customerStates), [
                            mb_strtolower(trim((string) $concern['summary'])),
                            mb_strtolower(trim((string) ($snapshot['intake']['concern_summary'] ?? ''))),
                        ], true);
                @endphp
                @include('operations.documents.partials._pdf-concern', [
                    'snapshot' => $snapshot,
                    'concern' => $concern,
                    'duplicateCustomerStates' => $duplicateCustomerStates,
                ])
            @endforeach
        </section>

        @include('operations.documents.partials._document-footer', [
            'snapshot' => $snapshot,
            'variant' => 'pdf',
        ])

    </main>
</body>
</html>
