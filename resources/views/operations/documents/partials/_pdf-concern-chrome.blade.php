{{-- Shared scope-card chrome for customer documents and operational sheets. --}}
.sheet-section-heading {
    margin: 0 0 0.08in;
    color: #334155;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.11em;
    line-height: 1;
    text-transform: uppercase;
}

.concern {
    position: relative;
    overflow: hidden;
    margin-top: 0.1in;
    border: 1px solid #e2e8f0;
    break-inside: avoid;
    page-break-inside: avoid;
}

.concern-header {
    padding: 0.04in 0.1in 0.045in 0.12in;
    border-bottom: 1px solid #e2e8f0;
    background: #fff;
}

.concern-header-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-rows: auto auto auto;
    column-gap: 0.1in;
    row-gap: 0.015in;
    align-items: start;
}

.concern-priority-badge {
    grid-column: 1 / -1;
    grid-row: 1;
    margin: 0 0 0.01in;
    font-size: 8px;
    font-weight: 800;
    letter-spacing: 0.1em;
    line-height: 1.15;
    text-transform: uppercase;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

        .concern-header-title {
            grid-column: 1;
            grid-row: 2;
            min-width: 0;
            color: #0f172a;
            font-size: 12.5px;
            font-weight: 700;
            line-height: 1.3;
            letter-spacing: -0.01em;
        }

.concern-header-total,
.concern-header-meta {
    grid-column: 2;
    grid-row: 2;
    margin: 0;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.2;
    text-align: right;
    white-space: nowrap;
}

.concern-header-subline {
    grid-column: 1;
    grid-row: 3;
    min-width: 0;
}

.concern-header-support {
    margin: 0;
    color: #64748b;
    font-size: 10px;
    font-weight: 500;
    line-height: 1.5;
    white-space: pre-line;
}

.ops-note-body {
    display: block;
    min-width: 0;
    white-space: pre-line;
    line-height: 1.5;
    font-style: normal;
    font-weight: 500;
    word-break: break-word;
}

.ops-note-body--pdf {
    color: #334155;
    font-weight: 600;
}

.ops-note-body--staff {
    color: #64748b;
    font-size: 10px;
    font-weight: 500;
}

.concern-header-status {
    grid-column: 2;
    grid-row: 3;
    display: flex;
    justify-content: flex-end;
    align-items: center;
}

.narrative-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.1in;
    padding: 0.08in 0.11in 0 0.12in;
}

.narrative-block,
.recommendation {
    padding: 0.075in 0.11in 0 0.12in;
}

.narrative-label {
    margin-bottom: 0.015in;
    color: #64748b;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
}

.codes {
    margin-top: 0.02in;
    color: #64748b;
    font-size: 9px;
    line-height: 1.35;
}

.numeric {
    text-align: right;
    white-space: nowrap;
}

.line-list {
    border-top: 1px solid #f1f5f9;
}

.line-list .sheet-table {
    margin-top: 0;
}

.line-list .sheet-table th {
    padding: 0.04in 0.1in;
    border-top: none;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #64748b;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.line-list .sheet-table td {
    padding: 0.05in 0.1in;
    border-bottom: 1px solid #f1f5f9;
    font-size: 11px;
    font-weight: 600;
    vertical-align: top;
}

.worksheet-capture {
    margin: 0.08in 0.11in 0.1in;
    border: 1px dashed #cbd5e1;
    min-height: 0.45in;
    padding: 0.05in 0.06in;
    color: #94a3b8;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
}

@include('operations.documents.partials._pdf-intent-theme')
