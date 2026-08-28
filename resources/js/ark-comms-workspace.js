import { arkEchoEnabled, getArkEcho } from './ark-echo';

const POLL_MS = 4000;

function fragmentUrl() {
    return document.getElementById('ops-comms-workspace-live')?.dataset.fragmentUrl ?? '';
}

function syncListCount(count) {
    const title = document.getElementById('ops-comms-workspace');

    if (! title) {
        return;
    }

    let countEl = title.querySelector('[data-comms-workspace-count]');
    const value = Number(count ?? 0);

    if (value > 0) {
        if (! countEl) {
            countEl = document.createElement('span');
            countEl.className = 'ops-pressure-count ops-pressure-count--inline';
            countEl.dataset.commsWorkspaceCount = '';
            title.appendChild(countEl);
        }

        countEl.textContent = `(${value})`;
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

    section.innerHTML = html;
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
        // Read state catches up on the next open — stay quiet.
    });
}

export function initCommsWorkspace() {
    const root = document.getElementById('ops-comms-workspace-live');
    const url = fragmentUrl();

    if (! root || url === '') {
        return;
    }

    let inflight = false;
    let lastSignature = '';

    const applyPayload = (payload) => {
        if (! payload) {
            return;
        }

        const threadScroll = captureThreadScroll();
        const composerState = captureComposerState();
        const selectedKey = document.querySelector('.ops-comms-workspace__list-row--active')?.getAttribute('href') ?? null;

        replaceSection('ops-comms-workspace-list', payload.list ?? '');
        replaceSection('ops-comms-workspace-thread', payload.thread ?? '');
        replaceSection('ops-comms-workspace-context', payload.context ?? '');
        syncListCount(payload.list_count ?? 0);

        requestAnimationFrame(() => {
            restoreThreadScroll(threadScroll);
            restoreComposerState(composerState);
            markOpenConversationRead();

            if (selectedKey) {
                document.querySelectorAll('.ops-comms-workspace__list-row').forEach((row) => {
                    if (row.getAttribute('href') === selectedKey) {
                        row.classList.add('ops-comms-workspace__list-row--active');
                    }
                });
            }
        });
    };

    const refresh = async () => {
        if (inflight || document.hidden) {
            return;
        }

        inflight = true;

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (! response.ok) {
                return;
            }

            const payload = await response.json();
            const signature = String(payload.signature ?? '');

            if (signature !== '' && signature === lastSignature) {
                return;
            }

            lastSignature = signature;
            applyPayload(payload);
        } catch {
            // Polling backup when realtime misses — stay quiet.
        } finally {
            inflight = false;
        }
    };

    const bindRealtime = () => {
        if (! arkEchoEnabled()) {
            return;
        }

        const echo = getArkEcho();

        if (! echo) {
            return;
        }

        echo.private('operations.incoming-calls')
            .listen('.call.updated', refresh);

        echo.private('operations.conversations')
            .listen('.conversation.message.received', refresh);

        echo.private('operations.comms-interrupts')
            .listen('.comms.interrupt', refresh);
    };

    document.addEventListener('ark:call-queue-changed', refresh);

    bindRealtime();
    window.setInterval(refresh, POLL_MS);
    refresh();

    requestAnimationFrame(() => {
        scrollThreadToBottom();
        markOpenConversationRead();
    });
}
