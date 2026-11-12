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

    .ops-financial-master-detail__editor > :first-child {
        margin-top: 0;
    }
</style>
