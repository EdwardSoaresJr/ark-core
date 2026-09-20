import { registerKeyboardShortcut, initKeyboardShortcuts } from './ark-keyboard-shortcuts';
import { arkOpsToast, queueModeSwitchToast, showModeSwitchToastIfNeeded } from './ark-ops-toast';
import { formHasChanges, worksheetHasUnsavedChanges } from './ark-form-unsaved';

function worksheetRoot() {
    return document.querySelector('[data-worksheet-root]');
}

function worksheetData() {
    const root = worksheetRoot();

    if (!root || !window.Alpine?.$data) {
        return null;
    }

    return window.Alpine.$data(root);
}

function isWorkspaceDirty() {
    if (typeof window.ARK?.workspace?.syncDirty === 'function') {
        return window.ARK.workspace.syncDirty();
    }

    if (typeof window.ARK?.workspace?.hasUnsavedFormChanges === 'function') {
        const dirty = window.ARK.workspace.hasUnsavedFormChanges();
        window.ARK?.workspace?.setDirty?.(dirty);

        return dirty;
    }

    return worksheetHasUnsavedChanges();
}

async function saveDirtyWorksheetForms(worksheet) {
    if (!worksheet?.submitWorksheetForm) {
        return !isWorkspaceDirty();
    }

    const root = worksheetRoot();

    if (!root) {
        return !isWorkspaceDirty();
    }

    const forms = [...root.querySelectorAll('form[method="post"]')]
        .filter((form) => !form.closest('[data-ark-workspace-dirty="off"]'))
        .filter((form) => !form.matches('[data-lifecycle-form]'))
        .filter(formHasChanges);

    for (const form of forms) {
        const ok = await worksheet.submitWorksheetForm(form);

        if (!ok) {
            return false;
        }
    }

    return !isWorkspaceDirty();
}

export function arkRoModeControl(config = {}) {
    const mode = config.mode === 'builder' || config.mode === 'edit' ? 'edit' : 'review';

    return {
        mode,
        reviewUrl: config.reviewUrl,
        editUrl: config.editUrl,
        canToggle: !!config.canToggle,
        confirmOpen: false,
        pendingUrl: null,
        saving: false,

        init() {
            showModeSwitchToastIfNeeded();
        },

        modeLabel() {
            return this.mode === 'edit' ? 'Editing' : 'Viewing';
        },

        modeActionClass() {
            return this.mode === 'edit' ? 'ops-review-action--edit' : 'ops-review-action--review';
        },

        toggle() {
            if (!this.canToggle) {
                return;
            }

            const url = this.mode === 'edit' ? this.reviewUrl : this.editUrl;
            this.requestSwitch(url);
        },

        requestSwitch(url) {
            if (this.mode === 'edit' && isWorkspaceDirty()) {
                this.pendingUrl = url;
                this.confirmOpen = true;

                return;
            }

            this.navigate(url);
        },

        navigate(url) {
            const toastMessage = this.mode === 'edit' ? 'Viewing' : 'Editing';
            queueModeSwitchToast(toastMessage);
            window.location.assign(url);
        },

        cancel() {
            this.confirmOpen = false;
            this.pendingUrl = null;
        },

        discardAndSwitch() {
            window.ARK?.workspace?.setDirty?.(false);
            this.confirmOpen = false;
            const url = this.pendingUrl;
            this.pendingUrl = null;

            if (url) {
                this.navigate(url);
            }
        },

        async saveAndSwitch() {
            if (this.saving) {
                return;
            }

            this.saving = true;

            try {
                const ok = await saveDirtyWorksheetForms(worksheetData());

                if (!ok) {
                    arkOpsToast('Save failed — fix errors before switching.');

                    return;
                }

                this.confirmOpen = false;
                const url = this.pendingUrl;
                this.pendingUrl = null;

                if (url) {
                    this.navigate(url);
                }
            } finally {
                this.saving = false;
            }
        },
    };
}

function focusGlobalSearch() {
    const link = document.querySelector('.ops-global-search');

    if (link instanceof HTMLAnchorElement) {
        window.location.assign(link.href);
    }
}

function openNewIntake() {
    const link = document.querySelector('a.ops-topbar-primary[href*="intake"]');

    if (link instanceof HTMLAnchorElement) {
        window.location.assign(link.href);
    }
}

function toggleRoMode() {
    const control = document.querySelector('[data-ro-mode-control]');

    if (!control || !window.Alpine?.$data) {
        return;
    }

    const data = window.Alpine.$data(control);

    if (data?.toggle) {
        data.toggle();
    }
}

export function initRoModeControl() {
    initKeyboardShortcuts();

    registerKeyboardShortcut({
        id: 'global-search',
        key: '/',
        when: () => document.querySelector('.ops-global-search'),
        handler: focusGlobalSearch,
    });

    registerKeyboardShortcut({
        id: 'new-intake',
        key: 'n',
        when: () => document.querySelector('a.ops-topbar-primary[href*="intake"]'),
        handler: openNewIntake,
    });

    registerKeyboardShortcut({
        id: 'toggle-ro-mode',
        key: 'v',
        when: () => document.querySelector('[data-ro-mode-control]'),
        handler: toggleRoMode,
    });
}
