/**
 * Lightweight keyboard shortcut registry for ARK operations surfaces.
 * Foundation for / search, N new intake, V toggle view/edit — not a command palette.
 */

const shortcuts = [];

let bound = false;

function shouldIgnoreKeyboardShortcut(event) {
    if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey) {
        return true;
    }

    const target = event.target;

    if (!(target instanceof Element)) {
        return false;
    }

    if (target.closest('[contenteditable="true"]')) {
        return true;
    }

    if (target.closest('[role="listbox"], [role="menu"], .hs-dropdown-open')) {
        return true;
    }

    const field = target.closest('input, textarea, select');

    if (!field) {
        return false;
    }

    if (field instanceof HTMLInputElement && ['hidden', 'button', 'submit'].includes(field.type)) {
        return false;
    }

    return true;
}

function matchesShortcut(event, shortcut) {
    if (event.key.toLowerCase() !== shortcut.key.toLowerCase()) {
        return false;
    }

    if (typeof shortcut.when === 'function' && !shortcut.when()) {
        return false;
    }

    return true;
}

function onKeydown(event) {
    if (shouldIgnoreKeyboardShortcut(event)) {
        return;
    }

    for (const shortcut of shortcuts) {
        if (!matchesShortcut(event, shortcut)) {
            continue;
        }

        event.preventDefault();
        shortcut.handler(event);

        return;
    }
}

export function registerKeyboardShortcut(shortcut) {
    shortcuts.push(shortcut);
}

export function initKeyboardShortcuts() {
    if (bound) {
        return;
    }

    bound = true;
    window.addEventListener('keydown', onKeydown);
}
