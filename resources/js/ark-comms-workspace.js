import { arkEchoEnabled, getArkEcho } from './ark-echo';
import { arkRealtimeSync } from './ark-realtime-sync';

const POLL_MS = 4000;
const LIST_ROW = '[data-comms-list-row], .ops-comms-workspace__list-row';
const ACTIVE_LIST_ROW = '[data-comms-list-row].active, .ops-comms-workspace__list-row--active';

function fragmentUrl(signature = '') {
    const base = document.getElementById('ops-comms-workspace-live')?.dataset.fragmentUrl ?? '';

    if (base === '' || signature === '') {
        return base;
    }

    const url = new URL(base, window.location.origin);
    url.searchParams.set('signature', signature);

    return `${url.pathname}${url.search}`;
}

function syncListCount(count) {
    const title = document.getElementById('ops-comms-workspace');

    if (! title) {
        return;
    }

    const tabler = title.classList.contains('page-title');
    let countEl = title.querySelector('[data-comms-workspace-count]')
        || title.querySelector('[data-page-title-count]')
        || title.querySelector('.ops-pressure-count');
    const value = Number(count ?? 0);

    if (value > 0) {
        if (! countEl) {
            countEl = document.createElement('span');
            countEl.className = tabler ? 'badge bg-red-lt ms-1' : 'ops-pressure-count ops-pressure-count--inline';
            countEl.dataset.commsWorkspaceCount = '';
            title.appendChild(countEl);
        }

        countEl.dataset.commsWorkspaceCount = '';
        countEl.textContent = tabler ? String(value) : `(${value})`;
        countEl.hidden = false;
    } else if (countEl) {
        countEl.textContent = '';
        countEl.hidden = true;
    }
}

function replaceSection(sectionId, html) {
    const section = document.getElementById(sectionId);

    if (! section || typeof html !== 'string' || html === '') {
        return;
    }

    if (window.Alpine?.destroyTree) {
        window.Alpine.destroyTree(section);
    }

    section.innerHTML = html;

    if (window.Alpine?.initTree) {
        window.Alpine.initTree(section);
    }
}

function listItemsEl() {
    return document.querySelector('#ops-comms-workspace-list [data-comms-list-items], #ops-comms-workspace-list .ops-comms-workspace__list-items');
}

function captureListScroll() {
    return listItemsEl()?.scrollTop ?? 0;
}

function restoreListScroll(top) {
    const el = listItemsEl();

    if (el) {
        el.scrollTop = top;
    }
}

function hrefKey(href) {
    if (! href) {
        return '';
    }

    try {
        const url = new URL(href, window.location.origin);

        return `${url.pathname}${url.search}`;
    } catch {
        return href;
    }
}

const SELECTION_FEEDBACK_MS = 180;

function wait(ms) {
    if (ms <= 0) {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        window.setTimeout(resolve, ms);
    });
}

function markSelectedRow(href) {
    if (! href) {
        return;
    }

    const selected = hrefKey(href);

    document.querySelectorAll(LIST_ROW).forEach((row) => {
        const active = hrefKey(row.getAttribute('href')) === selected;

        if (row.hasAttribute('data-comms-list-row')) {
            row.classList.toggle('active', active);

            if (active) {
                row.setAttribute('aria-current', 'true');
            } else {
                row.removeAttribute('aria-current');
            }

            return;
        }

        row.classList.toggle('ops-comms-workspace__list-row--active', active);
    });
}

function activeListRowHref() {
    return document.querySelector(ACTIVE_LIST_ROW)?.getAttribute('href') ?? null;
}

let selectionSwitching = false;
let actionBusyDepth = 0;

function paintActionBubble() {
    const thread = document.getElementById('ops-comms-workspace-thread');

    if (! thread) {
        return;
    }

    const active = selectionSwitching || actionBusyDepth > 0;

    thread.classList.toggle('is-switching', active);
    thread.querySelector('[data-comms-switch]')?.classList.toggle('d-none', ! active);

    if (active) {
        thread.setAttribute('aria-busy', 'true');
    } else {
        thread.removeAttribute('aria-busy');
    }
}

function setSelectionSwitching(active) {
    selectionSwitching = active;
    paintActionBubble();
}

function setActionBusy(active) {
    actionBusyDepth += active ? 1 : -1;

    if (actionBusyDepth < 0) {
        actionBusyDepth = 0;
    }

    paintActionBubble();
}

function selectionFeedbackRemaining(startedAt, now = performance.now()) {
    return Math.max(0, SELECTION_FEEDBACK_MS - (now - startedAt));
}

function syncFragmentUrl(nextUrl) {
    const live = document.getElementById('ops-comms-workspace-live');

    if (! live) {
        return;
    }

    const fragment = new URL(live.dataset.fragmentUrl || nextUrl.href, window.location.origin);

    ['conversation', 'lead', 'call', 'filter', 'section', 'platform_conversation', 'owner'].forEach((key) => {
        if (nextUrl.searchParams.has(key)) {
            fragment.searchParams.set(key, nextUrl.searchParams.get(key));
        } else if (key === 'conversation' || key === 'lead' || key === 'call' || key === 'platform_conversation') {
            fragment.searchParams.delete(key);
        }
    });

    fragment.searchParams.delete('signature');
    live.dataset.fragmentUrl = `${fragment.pathname}${fragment.search}`;
}

function selectionHref(url) {
    return url.searchParams.has('conversation')
        || url.searchParams.has('lead')
        || url.searchParams.has('call')
        || url.searchParams.has('platform_conversation');
}

function setThreadOpen(open) {
    const workspace = document.querySelector('[data-comms-workspace], .ops-comms-workspace');
    const state = window.Alpine?.$data?.(workspace) ?? workspace?._x_dataStack?.[0];

    if (! state || ! ('threadOpen' in state)) {
        return;
    }

    state.threadOpen = Boolean(open);
}

function threadMessagesEl() {
    return document.getElementById('comms-workspace-thread-messages');
}

function captureThreadScroll() {
    const el = threadMessagesEl();

    if (! el) {
        return null;
    }

    return {
        distanceFromBottom: el.scrollHeight - el.scrollTop - el.clientHeight,
    };
}

function scrollThreadToBottom() {
    const el = threadMessagesEl();

    if (! el) {
        return;
    }

    el.scrollTop = el.scrollHeight;
}

function restoreThreadScroll(state) {
    const el = threadMessagesEl();

    if (! el) {
        return;
    }

    if (state === null || state.distanceFromBottom < 96) {
        scrollThreadToBottom();

        return;
    }

    el.scrollTop = el.scrollHeight - el.clientHeight - state.distanceFromBottom;
}

function captureComposerState() {
    const active = document.activeElement;
    const composerRoot = document.getElementById('comms-thread-composer')
        || document.getElementById('comms-call-note-composer');

    if (! composerRoot) {
        return null;
    }

    const fields = {};
    composerRoot.querySelectorAll('textarea, input:not([type="hidden"])').forEach((field) => {
        if (! field.name) {
            return;
        }

        fields[field.name] = field.value;
    });

    const activeField = active && composerRoot.contains(active) && active.name
        ? {
            name: active.name,
            selectionStart: typeof active.selectionStart === 'number' ? active.selectionStart : null,
            selectionEnd: typeof active.selectionEnd === 'number' ? active.selectionEnd : null,
        }
        : null;

    return { fields, activeField };
}

function restoreComposerState(state) {
    if (! state) {
        return;
    }

    const composerRoot = document.getElementById('comms-thread-composer')
        || document.getElementById('comms-call-note-composer');

    if (! composerRoot) {
        return;
    }

    Object.entries(state.fields || {}).forEach(([name, value]) => {
        const field = composerRoot.querySelector(`[name="${CSS.escape(name)}"]`);

        if (field && (field.tagName === 'TEXTAREA' || field.tagName === 'INPUT') && ! field.value) {
            field.value = value;
        } else if (field && (field.tagName === 'TEXTAREA' || field.tagName === 'INPUT') && value) {
            field.value = value;
        }
    });

    if (! state.activeField?.name) {
        return;
    }

    const focusField = composerRoot.querySelector(`[name="${CSS.escape(state.activeField.name)}"]`);

    if (! focusField) {
        return;
    }

    focusField.focus({ preventScroll: true });

    if (
        typeof state.activeField.selectionStart === 'number'
        && typeof state.activeField.selectionEnd === 'number'
        && typeof focusField.setSelectionRange === 'function'
    ) {
        focusField.setSelectionRange(state.activeField.selectionStart, state.activeField.selectionEnd);
    }
}

let lastMarkedReadKey = '';

function markOpenConversationRead() {
    const body = threadMessagesEl();
    const markUrl = body?.dataset.markReadUrl ?? '';
    const key = body?.dataset.conversationKey ?? '';

    if (markUrl === '' || key === '' || key === lastMarkedReadKey) {
        return;
    }

    lastMarkedReadKey = key;

    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    fetch(markUrl, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: '{}',
    }).catch(() => {
        // Read state catches up on the next open - stay quiet.
    });
}

export function initCommsWorkspace() {
    const root = document.getElementById('ops-comms-workspace-live');
    const url = fragmentUrl();

    if (! root || url === '') {
        return;
    }

    let inflight = false;
    let selectionGeneration = 0;
    let lastSignature = root.dataset.pollSignature ?? '';

    const applyPayload = (payload, { replaceList = true, selectedHref = null } = {}) => {
        if (! payload || payload.unchanged) {
            return;
        }

        const threadScroll = captureThreadScroll();
        const composerState = captureComposerState();
        const listScroll = captureListScroll();
        const selectedKey = selectedHref
            ?? activeListRowHref();

        if (replaceList && typeof payload.list === 'string' && payload.list !== '') {
            replaceSection('ops-comms-workspace-list-body', payload.list);
        }
        if (typeof payload.thread === 'string' && payload.thread !== '') {
            replaceSection('ops-comms-workspace-thread', payload.thread);
        }
        if (typeof payload.context === 'string' && payload.context !== '') {
            replaceSection('ops-comms-workspace-context', payload.context);
        }
        if (payload.list_count !== undefined) {
            syncListCount(payload.list_count);
        }

        requestAnimationFrame(() => {
            restoreThreadScroll(threadScroll);
            restoreComposerState(composerState);
            restoreListScroll(listScroll);
            markOpenConversationRead();

            if (selectedKey) {
                markSelectedRow(selectedKey);
            }
        });
    };

    const fetchWorkspace = async (requestUrl) => {
        const response = await fetch(requestUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (! response.ok) {
            return null;
        }

        return response.json();
    };

    const openSelection = async (nextUrl, { push = true } = {}) => {
        const live = document.getElementById('ops-comms-workspace-live');

        if (! live) {
            return false;
        }

        const fragment = new URL(live.dataset.fragmentUrl || nextUrl.href, window.location.origin);

        ['conversation', 'lead', 'call', 'filter', 'section', 'platform_conversation', 'owner'].forEach((key) => {
            if (nextUrl.searchParams.has(key)) {
                fragment.searchParams.set(key, nextUrl.searchParams.get(key));
            } else if (key === 'conversation' || key === 'lead' || key === 'call' || key === 'platform_conversation') {
                fragment.searchParams.delete(key);
            }
        });

        fragment.searchParams.delete('signature');

        const selectedHref = `${nextUrl.pathname}${nextUrl.search}`;
        const currentHref = activeListRowHref() ?? '';
        const changing = hrefKey(currentHref) !== hrefKey(selectedHref);

        markSelectedRow(selectedHref);

        if (changing) {
            setSelectionSwitching(true);
        }

        inflight = true;
        selectionGeneration += 1;
        const generation = selectionGeneration;
        const startedAt = performance.now();
        const stillCurrent = () => generation === selectionGeneration;

        try {
            const payload = await fetchWorkspace(`${fragment.pathname}${fragment.search}`);

            if (! stillCurrent()) {
                return 'stale';
            }

            if (! payload || (payload.unchanged === true && ! payload.thread)) {
                setSelectionSwitching(false);

                if (payload?.unchanged === true) {
                    setThreadOpen(selectionHref(nextUrl));
                }

                return false;
            }

            setThreadOpen(selectionHref(nextUrl));

            if (changing) {
                await wait(selectionFeedbackRemaining(startedAt));
            }

            if (! stillCurrent()) {
                return 'stale';
            }

            lastSignature = String(payload.signature ?? lastSignature);
            setSelectionSwitching(false);
            applyPayload(payload, { replaceList: false, selectedHref });
            syncFragmentUrl(nextUrl);

            if (push) {
                window.history.pushState({ commsWorkspace: true }, '', selectedHref);
            }

            requestAnimationFrame(() => {
                scrollThreadToBottom();
                markOpenConversationRead();
            });

            return true;
        } catch {
            if (! stillCurrent()) {
                return 'stale';
            }

            setSelectionSwitching(false);

            return false;
        } finally {
            if (stillCurrent()) {
                inflight = false;
            }
        }
    };

    const refresh = async () => {
        if (inflight || document.hidden || actionBusyDepth > 0) {
            return;
        }

        inflight = true;
        const generation = selectionGeneration;

        try {
            const response = await fetch(fragmentUrl(lastSignature) || url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (! response.ok || generation !== selectionGeneration) {
                return;
            }

            const payload = await response.json();

            if (generation !== selectionGeneration) {
                return;
            }

            const signature = String(payload.signature ?? '');

            if (payload.unchanged) {
                lastSignature = signature || lastSignature;

                return;
            }

            if (signature !== '') {
                lastSignature = signature;
            }

            applyPayload(payload);
        } catch {
            // Polling backup when realtime misses - stay quiet.
        } finally {
            if (generation === selectionGeneration) {
                inflight = false;
            }
        }
    };

    const sync = arkRealtimeSync({ refresh, fastMs: POLL_MS, slowMs: 30000 });

    const bindRealtime = () => {
        if (! arkEchoEnabled()) {
            return;
        }

        const echo = getArkEcho();

        if (! echo) {
            return;
        }

        echo.private(window.arkShopChannel('operations.incoming-calls'))
            .listen('.call.updated', sync.trigger);

        echo.private(window.arkShopChannel('operations.conversations'))
            .listen('.conversation.message.received', sync.trigger);

        echo.private(window.arkShopChannel('operations.comms-interrupts'))
            .listen('.comms.interrupt', sync.trigger);
    };

    document.addEventListener('ark:call-queue-changed', sync.trigger);

    document.addEventListener('ark:comms-action-busy', (event) => {
        if (! document.getElementById('ops-comms-workspace-live')) {
            return;
        }

        setActionBusy(Boolean(event.detail?.active));
    });

    root.addEventListener('submit', (event) => {
        const form = event.target;

        if (! (form instanceof HTMLFormElement) || event.defaultPrevented) {
            return;
        }

        if ((form.method || 'get').toLowerCase() !== 'post') {
            return;
        }

        setActionBusy(true);
    });

    root.addEventListener('click', async (event) => {
        const row = event.target instanceof Element
            ? event.target.closest(LIST_ROW)
            : null;

        if (
            ! row
            || event.defaultPrevented
            || event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey
            || event.button !== 0
        ) {
            return;
        }

        const href = row.getAttribute('href');

        if (! href) {
            return;
        }

        const next = new URL(href, window.location.origin);

        if (! selectionHref(next)) {
            return;
        }

        event.preventDefault();

        const opened = await openSelection(next);

        if (opened === false) {
            window.location.assign(href);
        }
    });

    window.addEventListener('popstate', () => {
        if (! document.getElementById('ops-comms-workspace-live')) {
            return;
        }

        openSelection(new URL(window.location.href), { push: false });
    });

    bindRealtime();
    refresh();

    requestAnimationFrame(() => {
        scrollThreadToBottom();
        markOpenConversationRead();
    });
}
