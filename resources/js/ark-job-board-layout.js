/**
 * Job Board List | Cards switcher.
 * List = lane tabs + table. Cards = Kanban columns (List lane tabs do not constrain Cards).
 *
 * Authority: server `users.job_board_view` (PATCH profile.job-board-view.update).
 * Browser localStorage / legacy cookie are mirrors and one-time migration sources.
 * Does not rewrite ?view= into the URL. Does not use viewport to override a saved preference.
 */
import {
    UiLayoutModule,
    normalizeLayout,
    readExplicitLayout,
    readViewQueryParam,
    scopeFromDocument,
    stripViewQueryParam,
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

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
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

function setStatus(board, message, { isError = false } = {}) {
    const status = board.querySelector('[data-job-board-layout-status]');
    if (!status) {
        return;
    }
    if (!message) {
        status.textContent = '';
        status.classList.add('d-none');
        status.classList.remove('text-danger', 'text-secondary');
        return;
    }
    status.textContent = message;
    status.classList.remove('d-none');
    status.classList.toggle('text-danger', isError);
    status.classList.toggle('text-secondary', !isError);
}

function applyLayout(board, layout, { mode = null } = {}) {
    const nextMode = mode || layout;
    board.setAttribute('data-job-board-layout', nextMode);
    board.setAttribute('data-job-board-layout-explicit', '1');
    board.setAttribute('data-job-board-effective-layout', layout);
    syncToggle(board, layout);
}

async function persistLayout(board, layout, scope, sequence) {
    const url = board.getAttribute('data-job-board-view-persist-url');
    const token = csrfToken();
    if (!url || !token) {
        setStatus(board, 'Could not save view preference (missing security token).', { isError: true });
        return false;
    }

    try {
        const response = await fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ job_board_view: layout }),
            credentials: 'same-origin',
        });

        if (sequence !== Number(board.dataset.jobBoardLayoutPersistSeq || '0')) {
            // A newer switch superseded this request; ignore stale result.
            return true;
        }

        if (!response.ok) {
            let detail = 'Could not save view preference.';
            try {
                const payload = await response.json();
                if (payload?.message) {
                    detail = String(payload.message);
                } else if (payload?.errors?.job_board_view?.[0]) {
                    detail = String(payload.errors.job_board_view[0]);
                }
            } catch {
                // keep default message
            }
            setStatus(board, `${detail} Keeping the current view on screen.`, { isError: true });
            return false;
        }

        const payload = await response.json().catch(() => ({}));
        const saved = normalizeLayout(payload?.job_board_view) || layout;
        writeExplicitLayout(scope, saved, { cookieDomain: scope.cookieDomain });
        setStatus(board, '');
        return true;
    } catch {
        if (sequence !== Number(board.dataset.jobBoardLayoutPersistSeq || '0')) {
            return true;
        }
        setStatus(board, 'Could not save view preference. Keeping the current view on screen.', {
            isError: true,
        });
        return false;
    }
}

function initBoard(board) {
    if (board.dataset.jobBoardLayoutReady === '1') {
        return;
    }
    board.dataset.jobBoardLayoutReady = '1';
    board.dataset.jobBoardLayoutPersistSeq = '0';

    const scope = boardScope(board);
    const fromQuery = readViewQueryParam();
    const fromStore = readExplicitLayout(scope);
    const modeAttr = board.getAttribute('data-job-board-layout') || 'cards';
    const serverLayout = normalizeLayout(modeAttr) || 'cards';
    const serverExplicit = board.getAttribute('data-job-board-layout-explicit') === '1';

    let layout = serverLayout;

    if (fromQuery) {
        layout = fromQuery;
        stripViewQueryParam();
        applyLayout(board, layout);
        writeExplicitLayout(scope, layout, { cookieDomain: scope.cookieDomain });
        const seq = Number(board.dataset.jobBoardLayoutPersistSeq || '0') + 1;
        board.dataset.jobBoardLayoutPersistSeq = String(seq);
        void persistLayout(board, layout, scope, seq);
    } else if (serverExplicit) {
        layout = serverLayout;
        applyLayout(board, layout);
        writeExplicitLayout(scope, layout, { cookieDomain: scope.cookieDomain });
    } else if (fromStore) {
        // One-time migration of legacy browser preference when the account has none.
        layout = fromStore;
        applyLayout(board, layout);
        const seq = Number(board.dataset.jobBoardLayoutPersistSeq || '0') + 1;
        board.dataset.jobBoardLayoutPersistSeq = String(seq);
        void persistLayout(board, layout, scope, seq);
    } else {
        layout = 'cards';
        applyLayout(board, layout, { mode: 'cards' });
        board.setAttribute('data-job-board-layout-explicit', '0');
        writeExplicitLayout(scope, layout, { cookieDomain: scope.cookieDomain });
    }

    board.querySelectorAll('[data-job-board-layout-option]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const next = normalizeLayout(btn.getAttribute('data-job-board-layout-option'));
            if (!next) {
                return;
            }
            applyLayout(board, next);
            writeExplicitLayout(scope, next, { cookieDomain: scope.cookieDomain });
            const seq = Number(board.dataset.jobBoardLayoutPersistSeq || '0') + 1;
            board.dataset.jobBoardLayoutPersistSeq = String(seq);
            setStatus(board, 'Saving view…');

            // Focus is Cards navigation state. Leaving for List clears it so List tabs
            // stay authoritative and returning to Cards shows the full board.
            const url = new URL(window.location.href);
            const hadFocus = url.searchParams.has('focus');
            if (next === 'list' && hadFocus) {
                url.searchParams.delete('focus');
                url.searchParams.delete('focus_limit');
                void persistLayout(board, next, scope, seq).finally(() => {
                    window.location.assign(url.pathname + url.search + url.hash);
                });
                return;
            }

            void persistLayout(board, next, scope, seq);
        });
    });

    // Focus entry: scroll the focused workspace into view for keyboard / SR users.
    const focusRoot = board.querySelector('[data-job-board-focus]');
    if (focusRoot) {
        focusRoot.scrollIntoView({ behavior: 'auto', block: 'start' });
        const back = focusRoot.querySelector('[data-job-board-focus-back]');
        if (back instanceof HTMLElement) {
            back.focus({ preventScroll: true });
        }
    }

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
