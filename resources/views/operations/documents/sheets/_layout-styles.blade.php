<style>
    @page {
        size: Letter;
        margin: 0.32in 0.34in 0.4in;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        padding: 0;
        color: #0f172a;
        font-family: @include('operations.documents.partials._pdf-font-stack');
        font-size: 11px;
        line-height: 1.45;
    }

    h1, h2, h3, p { margin: 0; }

    h2 {
        font-size: 14px;
    }

    .sheet { width: 100%; }

    @include('operations.documents.partials._pdf-header-styles')

    @include('operations.documents.partials._pdf-concern-chrome')

    .section {
        margin-top: 0.1in;
    }

    .checkin-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.08in;
    }

    .checkin-grid .worksheet-capture {
        margin: 0;
        min-height: 0.5in;
    }

    .worksheet-capture--staff {
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        gap: 0.05in;
        min-height: 0.55in;
    }

    .staff-capture-label {
        color: #94a3b8;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .staff-capture-value {
        margin: 0;
        color: #334155;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.2;
    }

    .worksheet-capture--mileage {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 0.05in;
    }

    .mileage-capture-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.06in;
    }

    .mileage-capture-label {
        color: #94a3b8;
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .mileage-capture-verify {
        display: inline-flex;
        align-items: center;
        gap: 0.04in;
        color: #64748b;
        font-size: 8px;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .mileage-capture-value {
        margin: 0;
        color: #0f172a;
        font-size: 14px;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.01em;
        line-height: 1.1;
    }

    table.sheet-table {
        width: 100%;
        border-collapse: collapse;
    }

    table.sheet-table--tech-pull {
        table-layout: fixed;
    }

    table.sheet-table--tech-pull col.sheet-col-check { width: 0.32in; }
    table.sheet-table--tech-pull col.sheet-col-code { width: 0.82in; }
    table.sheet-table--tech-pull col.sheet-col-desc { width: 2.7in; }
    table.sheet-table--tech-pull col.sheet-col-vendor { width: 0.72in; }
    table.sheet-table--tech-pull col.sheet-col-qty { width: 0.52in; }
    table.sheet-table--tech-pull col.sheet-col-flag { width: 0.42in; }

    .line-list .sheet-table.sheet-table--tech-pull th,
    .line-list .sheet-table.sheet-table--tech-pull td,
    table.sheet-table--tech-pull th,
    table.sheet-table--tech-pull td {
        padding: 0.045in 0.05in;
        vertical-align: middle;
    }

    table.sheet-table--tech-pull th.sheet-col-check,
    table.sheet-table--tech-pull td.sheet-col-check {
        padding-left: 0.08in;
        padding-right: 0.03in;
        text-align: center;
    }

    table.sheet-table--tech-pull th.sheet-col-code,
    table.sheet-table--tech-pull td.sheet-col-code {
        padding-left: 0.04in;
        padding-right: 0.05in;
        overflow: hidden;
        text-align: left;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    table.sheet-table--tech-pull th.sheet-col-code {
        font-size: 8px;
        letter-spacing: 0.03em;
    }

    table.sheet-table--tech-pull td.sheet-col-code {
        color: #475569;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }

    table.sheet-table--tech-pull th.sheet-col-desc {
        padding-left: 0.05in;
        padding-right: 0.06in;
        text-align: left;
        white-space: nowrap;
    }

    table.sheet-table--tech-pull td.sheet-col-desc {
        padding-left: 0.05in;
        padding-right: 0.06in;
        color: #0f172a;
        font-size: 10.5px;
        font-weight: 600;
        line-height: 1.3;
        overflow-wrap: anywhere;
        text-align: left;
        white-space: normal;
        word-break: break-word;
    }

    table.sheet-table--tech-pull tbody td {
        vertical-align: top;
    }

    table.sheet-table--tech-pull th.sheet-col-vendor,
    table.sheet-table--tech-pull td.sheet-col-vendor {
        padding-left: 0.04in;
        padding-right: 0.05in;
        color: #64748b;
        font-size: 10px;
        text-align: left;
    }

    table.sheet-table--tech-pull th.sheet-col-qty,
    table.sheet-table--tech-pull td.sheet-col-qty {
        padding-left: 0.03in;
        padding-right: 0.06in;
        font-variant-numeric: tabular-nums;
        text-align: right;
        white-space: nowrap;
    }

    table.sheet-table--tech-pull th.sheet-col-flag,
    table.sheet-table--tech-pull td.sheet-col-flag {
        padding-left: 0.03in;
        padding-right: 0.06in;
        text-align: center;
    }

    table.sheet-table--tech-pull .sheet-cell-muted {
        color: #cbd5e1;
    }

    table.sheet-table--tech-pull tbody tr:first-child td {
        border-top: 1px solid #f1f5f9;
    }

    table.sheet-table--tech-pull tr.sheet-row-divider td {
        padding: 0.05in 0.08in;
        border-top: none;
        border-bottom: none;
        background: #fff;
    }

    table.sheet-table--tech-pull .sheet-table-hr {
        display: block;
        width: 100%;
        height: 0;
        margin: 0;
        border: 0;
        border-top: 1.5px solid #94a3b8;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .pull-box {
        display: inline-block;
        width: 0.11in;
        height: 0.11in;
        border: 1px solid #475569;
        vertical-align: middle;
    }

    .sheet-flag-cell {
        color: #64748b;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .sheet-flag-cell--yes {
        color: #b91c1c;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .flags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.04in;
        margin: 0.06in 0 0.1in;
    }

    .flag {
        border: 1px solid #cbd5e1;
        background: #fff;
        padding: 0.02in 0.05in;
        color: #334155;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .empty-state {
        margin-top: 0.1in;
        border: 1px dashed #cbd5e1;
        padding: 0.12in;
        color: #64748b;
        font-size: 11px;
        font-weight: 600;
        text-align: center;
    }

    .summary-row {
        display: flex;
        justify-content: flex-end;
        gap: 0.2in;
        margin-top: 0.1in;
        color: #334155;
        font-size: 11px;
        font-weight: 800;
    }
</style>
