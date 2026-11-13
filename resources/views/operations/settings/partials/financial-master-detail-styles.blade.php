{{-- Shared Financial Rules master-detail sizing for Labor, Parts Matrix, and Tax. --}}
<style>
    .ops-financial-master-detail {
        --ops-financial-nav-width: 240px;
    }

    @media (min-width: 992px) {
        .ops-financial-master-detail {
            display: flex;
            flex-wrap: nowrap;
            align-items: flex-start;
            gap: 1rem;
        }

        .ops-financial-master-detail__nav {
            flex: 0 0 var(--ops-financial-nav-width);
            width: var(--ops-financial-nav-width);
            max-width: var(--ops-financial-nav-width);
        }

        .ops-financial-master-detail__editor {
            flex: 1 1 auto;
            min-width: 0;
        }
    }

    .ops-financial-master-detail__nav .list-group-item {
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .ops-financial-master-detail__nav-item {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
        text-align: left;
    }

    .ops-financial-master-detail__nav-label {
        display: block;
        width: 100%;
        line-height: 1.25;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .ops-financial-master-detail__nav-item .badge {
        align-self: flex-start;
        white-space: normal;
        max-width: 100%;
        text-align: left;
    }

    .ops-financial-master-detail__editor > :first-child {
        margin-top: 0;
    }

    /* Labor Matrix hybrid: denser Billing Class rows (#194). */
    .labor-matrix-table--hybrid > :is(thead, tbody) > tr > :is(th, td) {
        padding-top: 0.4rem;
        padding-bottom: 0.4rem;
        vertical-align: middle;
    }

    .labor-matrix-table--hybrid .labor-matrix-th {
        /* Native Tabler tokens only — no light-mode hex fallbacks that flash white in dark theme. */
        background-color: var(--tblr-bg-surface-tertiary);
        color: var(--tblr-secondary);
        border-bottom-color: var(--tblr-border-color);
        font-weight: 600;
    }

    .labor-matrix-table--hybrid .form-hint {
        font-size: 0.7rem;
        line-height: 1.2;
        color: var(--tblr-secondary);
    }

    .labor-matrix-table--hybrid tbody tr:hover > td {
        background: color-mix(in srgb, var(--tblr-primary, #206bc4) 6%, transparent);
    }

    @media (max-width: 991.98px) {
        .labor-matrix-table--hybrid {
            min-width: 44rem;
        }
    }
</style>
