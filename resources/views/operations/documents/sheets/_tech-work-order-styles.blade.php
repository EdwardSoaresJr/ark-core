<style>
    @page {
        size: Letter;
        margin: 0.4in 0.45in 0.45in;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        padding: 0;
        color: #0f172a;
        font-family: @include('operations.documents.partials._pdf-font-stack');
        font-size: 12px;
        line-height: 1.4;
        background: #fff;
    }

    h1, h2, h3, p, ul, li { margin: 0; }

    .tech-wo {
        width: 100%;
    }

    .tech-wo-doc-title {
        color: #0f172a;
        font-size: 22px;
        font-weight: 900;
        letter-spacing: 0.06em;
        line-height: 1.1;
        text-transform: uppercase;
    }

    .tech-wo-meta {
        margin-top: 0.14in;
        color: #334155;
        font-size: 13px;
        font-weight: 700;
        line-height: 1.45;
    }

    .tech-wo-meta-line {
        margin: 0;
    }

    .tech-wo-meta-muted {
        color: #64748b;
        font-weight: 600;
    }

    .tech-wo-assignment {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.18in;
        margin-top: 0.22in;
    }

    .tech-wo-field-label {
        color: #64748b;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .tech-wo-field-value {
        margin-top: 0.03in;
        color: #0f172a;
        font-size: 16px;
        font-weight: 800;
        line-height: 1.2;
    }

    .tech-wo-flag-badge {
        margin-top: 0.24in;
        border: 2.5px solid #0f172a;
        padding: 0.16in 0.18in;
        text-align: center;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .tech-wo-flag-label {
        color: #0f172a;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }

    .tech-wo-flag-value {
        margin-top: 0.04in;
        color: #0f172a;
        font-size: 34px;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.02em;
        line-height: 1;
    }

    .tech-wo-flag-hint {
        margin-top: 0.06in;
        color: #64748b;
        font-size: 9px;
        font-weight: 600;
        letter-spacing: 0.02em;
    }

    .tech-wo-printed {
        margin-top: 0.1in;
        color: #94a3b8;
        font-size: 9px;
        font-weight: 600;
    }

    .tech-wo-concern {
        margin-top: 0.28in;
        page-break-inside: avoid;
    }

    .tech-wo-concern-title {
        margin-bottom: 0.12in;
        color: #0f172a;
        font-size: 18px;
        font-weight: 900;
        letter-spacing: 0.02em;
        line-height: 1.15;
        text-transform: uppercase;
    }

    .tech-wo-section {
        margin-top: 0.12in;
    }

    .tech-wo-section-label {
        margin-bottom: 0.06in;
        color: #0f172a;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }

    .tech-wo-checklist {
        list-style: none;
        padding: 0;
    }

    .tech-wo-checklist li {
        display: flex;
        align-items: flex-start;
        gap: 0.1in;
        padding: 0.055in 0;
        color: #0f172a;
        font-size: 13px;
        font-weight: 650;
        line-height: 1.35;
    }

    .tech-wo-check {
        display: inline-block;
        flex: 0 0 auto;
        width: 0.14in;
        height: 0.14in;
        margin-top: 0.02in;
        border: 1.5px solid #0f172a;
    }

    .tech-wo-check-text {
        flex: 1 1 auto;
    }

    .tech-wo-op {
        display: block;
        font-weight: 800;
        color: #0f172a;
    }

    .tech-wo-hours {
        display: block;
        margin-top: 0.02in;
        color: #475569;
        font-weight: 700;
    }

    .tech-wo-notes {
        margin-top: 0.04in;
    }

    .tech-wo-note {
        margin-top: 0.06in;
        color: #334155;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.4;
    }

    .tech-wo-note-label {
        color: #64748b;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .tech-wo-empty {
        margin-top: 0.28in;
        border: 1.5px dashed #94a3b8;
        padding: 0.2in;
        color: #64748b;
        font-size: 13px;
        font-weight: 700;
        text-align: center;
    }

    .tech-wo-shop {
        margin-bottom: 0.12in;
        color: #64748b;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
</style>
