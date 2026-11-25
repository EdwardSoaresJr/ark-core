/**
 * Job Board List | Cards switcher.
 * List = lane tabs + table. Cards = Kanban columns (List lane tabs do not constrain Cards).
 * Uses shared ark-ui-layout-preference (tenant + user + job_board).
 * Does not rewrite ?view= into the URL.
 */
import {
    MD_UP,
    UiLayoutModule,
    normalizeLayout,
    readExplicitLayout,
    readViewQueryParam,
    scopeFromDocument,
    stripViewQueryParam,
    viewportDefault,
    writeExplicitLayout,
} from './ark-ui-layout-preference';

function boardScope(board) {
    const docScope = scopeFromDocument(document);
    return {
        tenant: board.getAttribute('data-ark-ui-tenant') || docScope.tenant,
        userId: board.getAttribute('data-ark-ui-user') || docScope.userId,
        module: UiLayoutModule.JobBoard,
        cookieDomain: board.getAttribute('data-job-board-cookie-domain') || null,
    };
}

function syncToggle(board, layout) {
    board.querySelectorAll('[data-job-board-layout-option]').forEach((btn) => {
        const option = btn.getAttribute('data-job-board-layout-option');
        const active = option === layout;
        btn.classList.toggle('active', active);
        if (active) {
            btn.setAttribute('aria-current', 'true');
        } else {
            btn.removeAttribute('aria-current');
        }
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
}

function applyLayout(board, layout, { persist = false, mode = null, scope = null } = {}) {
    if (persist && scope) {
        writeExplicitLayout(scope, layout, { cookieDomain: scope.cookieDomain });
        board.setAttribute('data-job-board-layout', layout);
        board.setAttribute('data-job-board-layout-explicit', '1');
    } else if (mode) {
        board.setAttribute('data-job-board-layout', mode);
    }

    board.setAttribute('data-job-board-effective-layout', layout);
    syncToggle(board, layout);
}

function initBoard(board) {
    if (board.dataset.jobBoardLayoutReady === '1') {
        return;
    }
    board.dataset.jobBoardLayoutReady = '1';

    const scope = boardScope(board);
    const fromQuery = readViewQueryParam();
    const fromStore = readExplicitLayout(scope);
    const modeAttr = board.getAttribute('data-job-board-layout') || 'auto';
    const serverExplicit = normalizeLayout(modeAttr);

    let mode = 'auto';
    let layout = viewportDefault();

    if (fromQuery) {
        writeExplicitLayout(scope, fromQuery, { cookieDomain: scope.cookieDomain });
        mode = fromQuery;
        layout = fromQuery;
        stripViewQueryParam();
    } else if (fromStore) {
        mode = fromStore;
        layout = fromStore;
    } else if (serverExplicit) {
        mode = serverExplicit;
        layout = serverExplicit;
        // Migrate SSR/cookie-only preference into the scoped store.
        writeExplicitLayout(scope, serverExplicit, { cookieDomain: scope.cookieDomain });
    }

    board.setAttribute('data-job-board-layout-explicit', mode === 'auto' ? '0' : '1');
    applyLayout(board, layout, { persist: false, mode });

    board.querySelectorAll('[data-job-board-layout-option]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const next = normalizeLayout(btn.getAttribute('data-job-board-layout-option'));
            if (!next) {
                return;
            }
            applyLayout(board, next, { persist: true, scope });
        });
    });

    const columnSelect = board.querySelector('[data-job-board-kanban-column-select]');
    if (columnSelect) {
        columnSelect.addEventListener('change', () => {
            const key = columnSelect.value;
            const column = board.querySelector(`[data-job-board-kanban-column="${key}"]`);
            const scroller = board.querySelector('[data-job-board-kanban-scroller]');
            if (!column || !scroller) {
                return;
            }
            column.scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' });
            scroller.focus({ preventScroll: true });
        });
    }

    const media = window.matchMedia(MD_UP);
    const onViewportChange = () => {
        if (board.getAttribute('data-job-board-layout-explicit') === '1') {
            // Explicit List on a phone keeps compact mobile List — never force Cards.
            applyLayout(board, board.getAttribute('data-job-board-layout') === 'cards' ? 'cards' : 'list', {
                persist: false,
                mode: board.getAttribute('data-job-board-layout'),
            });
            return;
        }
        applyLayout(board, viewportDefault(), { persist: false, mode: 'auto' });
    };
    if (typeof media.addEventListener === 'function') {
        media.addEventListener('change', onViewportChange);
    } else if (typeof media.addListener === 'function') {
        media.addListener(onViewportChange);
    }
}

export function initJobBoardLayout(root = document) {
    root.querySelectorAll('[data-job-board]').forEach((board) => initBoard(board));
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initJobBoardLayout());
    } else {
        initJobBoardLayout();
    }
}
