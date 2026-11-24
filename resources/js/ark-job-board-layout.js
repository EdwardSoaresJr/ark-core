/**
 * Job Board List | Cards switcher: client toggle without reload, cookie persistence.
 */
const COOKIE_NAME = 'ark_job_board_layout';
const MD_UP = '(min-width: 768px)';

function readCookie(name) {
    const prefix = `${name}=`;
    const parts = document.cookie.split(';');
    for (const part of parts) {
        const trimmed = part.trim();
        if (trimmed.startsWith(prefix)) {
            return decodeURIComponent(trimmed.slice(prefix.length));
        }
    }
    return null;
}

function writeCookie(name, value, domain) {
    const maxAge = 60 * 60 * 24 * 365;
    let cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=${maxAge}; samesite=lax`;
    if (window.location.protocol === 'https:') {
        cookie += '; secure';
    }
    if (domain) {
        cookie += `; domain=${domain}`;
    }
    document.cookie = cookie;
}

function viewportDefault() {
    return window.matchMedia(MD_UP).matches ? 'list' : 'cards';
}

function normalizeLayout(value) {
    if (value === 'cards' || value === 'board') {
        return 'cards';
    }
    if (value === 'list') {
        return 'list';
    }
    return null;
}

function effectiveLayout(board) {
    const mode = board.getAttribute('data-job-board-layout') || 'auto';
    if (mode === 'list' || mode === 'cards') {
        return mode;
    }
    return viewportDefault();
}

function syncHiddenInputs(board, layout) {
    board.querySelectorAll('[data-job-board-view-input]').forEach((input) => {
        input.value = layout;
        input.disabled = false;
    });
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

function applyLayout(board, layout, { persist = false, mode = null } = {}) {
    if (persist) {
        board.setAttribute('data-job-board-layout', layout);
        board.setAttribute('data-job-board-layout-explicit', '1');
        writeCookie(COOKIE_NAME, layout, board.getAttribute('data-job-board-cookie-domain') || undefined);
    } else if (mode) {
        board.setAttribute('data-job-board-layout', mode);
    }

    board.setAttribute('data-job-board-effective-layout', layout);
    syncToggle(board, layout);
    syncHiddenInputs(board, layout);
}

function initBoard(board) {
    if (board.dataset.jobBoardLayoutReady === '1') {
        return;
    }
    board.dataset.jobBoardLayoutReady = '1';

    const cookieLayout = normalizeLayout(readCookie(COOKIE_NAME));
    const modeAttr = board.getAttribute('data-job-board-layout') || 'auto';
    const explicitMode = normalizeLayout(modeAttr);
    const mode = explicitMode ? explicitMode : (cookieLayout ? cookieLayout : 'auto');
    const layout = explicitMode || cookieLayout || viewportDefault();

    board.setAttribute('data-job-board-layout-explicit', explicitMode || cookieLayout ? '1' : '0');
    applyLayout(board, layout, { persist: false, mode: explicitMode || cookieLayout || 'auto' });

    board.querySelectorAll('[data-job-board-layout-option]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const next = normalizeLayout(btn.getAttribute('data-job-board-layout-option'));
            if (!next) {
                return;
            }
            applyLayout(board, next, { persist: true });
        });
    });

    const media = window.matchMedia(MD_UP);
    const onViewportChange = () => {
        if (board.getAttribute('data-job-board-layout-explicit') === '1') {
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
