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

const NEAR_BOTTOM = 96;
const DRAFT_PREFIX = 'ark-comms-draft:';

function threadKey() {
    return threadMessagesEl()?.dataset.conversationKey ?? '';
}

function distanceFromBottom(el) {
    return el.scrollHeight - el.scrollTop - el.clientHeight;
}

function composerFields() {
    return [...document.querySelectorAll('#ops-comms-workspace-thread [data-comms-composer] textarea')]
        .filter((field) => field.id || field.name);
}

function saveDraft() {
    const key = threadKey();

    if (key === '') {
        return;
    }

    const draft = {};
    composerFields().forEach((field) => {
        if (field.value.trim() !== '') {
            draft[field.id || field.name] = field.value;
        }
    });

    try {
        if (Object.keys(draft).length === 0) {
            sessionStorage.removeItem(DRAFT_PREFIX + key);
        } else {
            sessionStorage.setItem(DRAFT_PREFIX + key, JSON.stringify(draft));
        }
    } catch {
        // Private browsing without storage: drafts last until the page changes.
    }
}

function restoreDraft() {
    const key = threadKey();
    let draft = null;

    try {
        draft = key === '' ? null : JSON.parse(sessionStorage.getItem(DRAFT_PREFIX + key) || 'null');
    } catch {
        draft = null;
    }

    if (! draft) {
        return;
    }

    composerFields().forEach((field) => {
        const value = draft[field.id || field.name];

        if (typeof value === 'string' && field.value === '') {
            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
}

function forgetDraftField(field) {
    const key = threadKey();

    if (key === '' || ! field) {
        return;
    }

    try {
        const draft = JSON.parse(sessionStorage.getItem(DRAFT_PREFIX + key) || 'null');

        if (! draft) {
            return;
        }

        delete draft[field.id || field.name];

        if (Object.keys(draft).length === 0) {
            sessionStorage.removeItem(DRAFT_PREFIX + key);
        } else {
            sessionStorage.setItem(DRAFT_PREFIX + key, JSON.stringify(draft));
        }
    } catch {
        // Nothing stored.
    }
}

function lastEntryKey(scope = document) {
    const entries = scope.querySelectorAll('#comms-workspace-thread-messages [data-comms-entry], [data-comms-thread-body] [data-comms-entry]');

    return entries.length > 0 ? entries[entries.length - 1].dataset.commsEntry : '';
}

/**
 * Appends the refreshed thread's entries that came after the newest one on screen; returns how many.
 */
function appendNewEntries(threadHtml, afterKey) {
    const body = threadMessagesEl();

    if (! body || afterKey === '') {
        return 0;
    }

    const template = document.createElement('template');
    template.innerHTML = threadHtml;
    const freshBody = template.content.querySelector('[data-comms-thread-body]');

    if (! freshBody) {
        return 0;
    }

    const children = [...freshBody.children];
    const at = children.findIndex((child) => child.dataset?.commsEntry === afterKey);

    if (at === -1) {
        return 0;
    }

    const fresh = children.slice(at + 1).filter((child) => ! child.dataset?.commsEntry || ! body.querySelector(`[data-comms-entry="${CSS.escape(child.dataset.commsEntry)}"]`));
    const lastDate = [...body.querySelectorAll('[data-comms-date]')].pop()?.dataset.commsDate;
    fresh.forEach((child) => {
        if (child.dataset?.commsDate && child.dataset.commsDate === lastDate) {
            return;
        }
        body.appendChild(child);
    });

    return fresh.filter((child) => child.dataset?.commsEntry).length;
}

function newBelowButton() {
    return document.querySelector('#ops-comms-workspace-thread [data-comms-new-below]');
}

function showNewBelow() {
    newBelowButton()?.classList.remove('d-none');
}

function hideNewBelow() {
    newBelowButton()?.classList.add('d-none');
}

function syncViewportHeight() {
    const viewport = window.visualViewport;

    if (viewport) {
        document.documentElement.style.setProperty('--ark-viewport-height', `${Math.round(viewport.height)}px`);
    }
}

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
        const keyBefore = threadKey();
        const newestBefore = lastEntryKey();
        const thread = threadMessagesEl();
        // A reader who loaded earlier messages and is reading them keeps them: the refresh would cut the thread back
        // to its latest page. New messages still arrive live, with the indicator.
        const keepThread = selectedHref === null && thread?.dataset.extended === '1'
            && threadScroll !== null && threadScroll.distanceFromBottom >= NEAR_BOTTOM;

        if (replaceList && typeof payload.list === 'string' && payload.list !== '') {
            replaceSection('ops-comms-workspace-list-body', payload.list);
        }
        let appended = 0;
        if (typeof payload.thread === 'string' && payload.thread !== '' && ! keepThread) {
            replaceSection('ops-comms-workspace-thread', payload.thread);
        } else if (keepThread && typeof payload.thread === 'string' && payload.thread !== '') {
            appended = appendNewEntries(payload.thread, newestBefore);
        }
        const sameConversation = keyBefore !== '' && keyBefore === threadKey();
        if (typeof payload.context === 'string' && payload.context !== '') {
            replaceSection('ops-comms-workspace-context', payload.context);
        }
        if (payload.list_count !== undefined) {
            syncListCount(payload.list_count);
        }

        requestAnimationFrame(() => {
            if (! keepThread) {
                restoreThreadScroll(sameConversation ? threadScroll : null);
            }
            if (sameConversation) {
                restoreComposerState(composerState);
            } else {
                restoreDraft();
            }
            restoreListScroll(listScroll);
            markOpenConversationRead();

            const newArrived = keepThread ? appended > 0 : (newestBefore !== '' && lastEntryKey() !== newestBefore);
            if (sameConversation && newArrived && threadScroll !== null && threadScroll.distanceFromBottom >= NEAR_BOTTOM) {
                showNewBelow();
            }

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

        const conversations = echo.private(window.arkShopChannel('operations.conversations'))
            .listen('.conversation.message.received', sync.trigger);

        let liveEvents = [];
        try {
            liveEvents = JSON.parse(document.getElementById('ops-comms-workspace-live')?.dataset.liveEvents || '[]');
        } catch {
            liveEvents = [];
        }
        liveEvents
            .filter((name) => typeof name === 'string' && name !== '')
            .forEach((name) => conversations.listen(name.startsWith('.') ? name : `.${name}`, sync.trigger));

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

    let loadingOlder = false;

    const loadOlder = async () => {
        const body = threadMessagesEl();
        const marker = body?.querySelector('[data-comms-older]');
        const live = document.getElementById('ops-comms-workspace-live');

        if (! body || ! marker || ! live || loadingOlder) {
            return;
        }

        loadingOlder = true;
        const button = marker.querySelector('[data-comms-older-load]');
        button?.classList.add('disabled');
        const request = new URL(live.dataset.fragmentUrl || url, window.location.origin);
        request.searchParams.set('older_before', marker.dataset.before ?? '');
        request.searchParams.set('older_key', marker.dataset.key ?? '');
        request.searchParams.set('loaded', marker.dataset.loaded ?? '0');
        request.searchParams.delete('signature');

        try {
            const payload = await fetchWorkspace(`${request.pathname}${request.search}`);

            if (! payload || threadMessagesEl() !== body || typeof payload.older_html !== 'string') {
                return;
            }

            const heightBefore = body.scrollHeight;
            const topBefore = body.scrollTop;
            marker.insertAdjacentHTML('afterend', payload.older_html);

            let previous = null;
            body.querySelectorAll('[data-comms-date]').forEach((line) => {
                if (previous && previous.dataset.commsDate === line.dataset.commsDate) {
                    line.remove();
                } else {
                    previous = line;
                }
            });

            body.scrollTop = topBefore + (body.scrollHeight - heightBefore);
            body.dataset.extended = '1';
            marker.dataset.loaded = String(Number(marker.dataset.loaded || 0) + Number(payload.count || 0));

            if (payload.older) {
                marker.dataset.before = payload.older.before;
                marker.dataset.key = payload.older.key;
            } else {
                marker.remove();
            }
        } catch {
            // The button stays for another try.
        } finally {
            loadingOlder = false;
            button?.classList.remove('disabled');
        }
    };

    root.addEventListener('scroll', (event) => {
        const body = event.target;

        if (! (body instanceof Element) || ! body.matches('[data-comms-thread-body]')) {
            return;
        }

        if (distanceFromBottom(body) < NEAR_BOTTOM) {
            hideNewBelow();
        }

        if (body.scrollTop < 150 && body.querySelector('[data-comms-older]')) {
            loadOlder();
        }
    }, true);

    root.addEventListener('input', (event) => {
        if (event.target instanceof HTMLTextAreaElement && event.target.closest('[data-comms-composer]')) {
            saveDraft();
        }
    });

    root.addEventListener('ark:composer-sent', (event) => {
        const composer = event.target instanceof Element ? event.target.closest('[data-ark-conversation-composer], #comms-thread-composer') : null;
        forgetDraftField(composer?.querySelector('textarea') ?? null);
    });

    root.addEventListener('submit', (event) => {
        if (event.target instanceof HTMLFormElement && event.target.closest('[data-comms-composer]')) {
            forgetDraftField(event.target.querySelector('textarea'));
        }
    });

    document.addEventListener('ark:comms-new-below', showNewBelow);

    window.visualViewport?.addEventListener('resize', syncViewportHeight);
    syncViewportHeight();

    let loadingMore = false;

    const loadMore = async (link) => {
        const live = document.getElementById('ops-comms-workspace-live');

        if (loadingMore || inflight || ! live || ! link) {
            return;
        }

        loadingMore = true;
        inflight = true;
        const generation = selectionGeneration;
        const fragment = new URL(live.dataset.fragmentUrl || url, window.location.origin);
        fragment.searchParams.set('shown', link.dataset.shown ?? '');
        fragment.searchParams.delete('signature');
        link.classList.add('disabled');

        try {
            const payload = await fetchWorkspace(`${fragment.pathname}${fragment.search}`);

            if (! payload || generation !== selectionGeneration) {
                return;
            }

            live.dataset.fragmentUrl = `${fragment.pathname}${fragment.search}`;
            lastSignature = String(payload.signature ?? lastSignature);
            applyPayload(payload);

            const page = new URL(window.location.href);
            page.searchParams.set('shown', link.dataset.shown ?? '');
            window.history.replaceState(window.history.state, '', `${page.pathname}${page.search}`);
        } catch {
            link.classList.remove('disabled');
        } finally {
            loadingMore = false;

            if (generation === selectionGeneration) {
                inflight = false;
            }
        }
    };

    root.addEventListener('scroll', (event) => {
        const list = event.target;

        if (! (list instanceof Element) || ! list.matches('[data-comms-list-items]')) {
            return;
        }

        if (list.scrollHeight - list.scrollTop - list.clientHeight < 240) {
            loadMore(list.querySelector('[data-comms-list-more]'));
        }
    }, true);

    root.addEventListener('click', async (event) => {
        if (event.target instanceof Element && event.target.closest('[data-comms-older-load]')) {
            event.preventDefault();
            loadOlder();

            return;
        }

        if (event.target instanceof Element && event.target.closest('[data-comms-new-below]')) {
            scrollThreadToBottom();
            hideNewBelow();

            return;
        }

        const more = event.target instanceof Element
            ? event.target.closest('[data-comms-list-more]')
            : null;

        if (more && ! event.defaultPrevented && event.button === 0 && ! event.metaKey && ! event.ctrlKey) {
            event.preventDefault();
            loadMore(more);

            return;
        }

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
        restoreDraft();
        markOpenConversationRead();
    });
}
