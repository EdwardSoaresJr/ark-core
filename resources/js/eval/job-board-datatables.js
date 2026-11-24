/**
 * Eval-only Job Board DataTables initializer (ES module).
 * Waits for CDN jQuery + DataTables globals, then binds the prototype table.
 */
function bootJobBoardDataTables() {
    const root = document.querySelector('[data-ark-dt-prototype]');
    if (!root) {
        return;
    }

    if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn?.DataTable !== 'function') {
        window.setTimeout(bootJobBoardDataTables, 50);
        return;
    }

    const $ = window.jQuery;
    const table = root.querySelector('#ark-job-board-dt');
    const dataUrl = root.getAttribute('data-dt-url');
    const filterForm = root.querySelector('[data-job-board-filters]');
    const summary = root.querySelector('[data-job-board-list-range]');
    const cardsView = root.querySelector('[data-job-board-view="cards"]');
    const listView = root.querySelector('[data-job-board-view="list"]');

    if (!table || !dataUrl || $.fn.dataTable.isDataTable(table)) {
        return;
    }

    function filterParams() {
        const params = {};
        if (!filterForm) {
            return params;
        }
        const fd = new FormData(filterForm);
        for (const [key, value] of fd.entries()) {
            if (value !== '') {
                params[key] = value;
            }
        }
        return params;
    }

    const dt = $(table).DataTable({
        serverSide: true,
        processing: true,
        searching: false,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[8, 'desc']],
        autoWidth: false,
        deferRender: true,
        layout: {
            topStart: 'pageLength',
            topEnd: null,
            bottomStart: 'info',
            bottomEnd: 'paging',
        },
        ajax: {
            url: dataUrl,
            data(d) {
                Object.assign(d, filterParams());
            },
        },
        columns: [
            { data: 'ro', orderable: true },
            { data: 'customer', orderable: true },
            { data: 'concern', orderable: false },
            { data: 'status', orderable: true },
            { data: 'tech', orderable: true },
            { data: 'total', orderable: true, className: 'text-end' },
            { data: 'attention', orderable: true },
            { data: 'activity', orderable: false },
            { data: 'updated', orderable: true },
            { data: 'open', orderable: false, className: 'w-1' },
        ],
        drawCallback(settings) {
            const json = settings.json;
            if (json?.meta && summary) {
                const from = json.recordsFiltered === 0 ? 0 : (json.meta.page - 1) * json.meta.per_page + 1;
                const to = Math.min(json.recordsFiltered, json.meta.page * json.meta.per_page);
                summary.textContent =
                    json.recordsFiltered === 0
                        ? 'No matching repair orders'
                        : `Showing ${from}–${to} of ${json.recordsFiltered} · Queue total ${json.meta.queue_total}`;
            }
        },
    });

    filterForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        dt.ajax.reload();
    });
    filterForm?.querySelector('[name="tech"]')?.addEventListener('change', () => dt.ajax.reload());

    root.querySelectorAll('[data-job-board-layout-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const mode = btn.getAttribute('data-job-board-layout-toggle');
            root.querySelectorAll('[data-job-board-layout-toggle]').forEach((b) => {
                const on = b === btn;
                b.classList.toggle('active', on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            if (mode === 'cards') {
                listView?.classList.add('d-none');
                cardsView?.classList.remove('d-none');
            } else {
                cardsView?.classList.add('d-none');
                listView?.classList.remove('d-none');
                dt.columns.adjust();
            }
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootJobBoardDataTables);
} else {
    bootJobBoardDataTables();
}
