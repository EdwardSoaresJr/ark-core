/**
 * Repair Order authoring chrome — advisors see task titles only (Add Work, Edit Labor).
 * Presentation stays on the Builder; this modal authors through existing worksheet continuity.
 */
import { arkSelectRepairOrderWorkspaceTab } from './ark-ro-workspace-tabs';

function ensureBuilderTabBeforeModalOpen(callback) {
    const tabsRoot = document.getElementById('repair-order-workspace-tabs');
    const modalHost = document.getElementById('workspace-modal-host');

    if (! tabsRoot || ! modalHost) {
        callback();

        return;
    }

    const tabData = window.Alpine?.$data?.(tabsRoot);

    if (! tabData || tabData.workspaceMode === 'builder' || tabData.tabs?.length === 1) {
        callback();

        return;
    }

    if (tabData.tab === 'builder') {
        callback();

        return;
    }

    arkSelectRepairOrderWorkspaceTab('builder');

    requestAnimationFrame(() => {
        requestAnimationFrame(callback);
    });
}

export function arkWorkspaceModal(config = {}) {
    const titles = {
        'add-work': 'What would you like to add?',
        concern: 'Add Concern',
        labor: 'Add Labor',
        part: 'Add Part',
        sublet: 'Add Sublet',
        note: 'Add Note',
        oil: 'Engine Oil Service',
        testing: 'Testing Package',
        'saved-work': 'Common Job',
        evidence: 'Photo',
        document: 'Paperwork',
        'hub-document': 'Add Document',
        'edit-line': 'Edit Line',
        'repair-action': 'Add Repair Action',
        'visit-reason': 'Reason for Visit',
        'concern-narrative': 'Narrative',
        'dragon-service-advisor': 'Rewrite notes',
        'dragon-service-advisor-visit-reason': 'Rewrite reason for visit',
        'dragon-service-advisor-line-note': 'Rewrite note',
        'review-estimate-notes': 'Review Estimate Notes',
        'concern-disposition': 'Customer Decision',
        'concern-billing': 'Billing',
        'concern-intent': 'Recommendation Status',
        'concern-production': 'Production Status',
        'repair-action-meta': 'Repair Action',
        'customer-identity': 'Customer',
        'vehicle-identity': 'Vehicle',
        mileage: 'Mileage',
        'visit-posture': 'Visit Type',
        'hub-customer': 'Customer',
        'hub-vehicle': 'Vehicle',
        'hub-vehicle-create': 'Add Vehicle',
        'review-request': 'Request a Review',
    };

    const helpers = {
        'add-work': '',
        concern: 'What problem or request are we adding?',
        labor: 'What labor belongs to this Repair Action?',
        part: 'What part belongs to this Repair Action?',
        sublet: 'What outside service belongs here?',
        note: '',
        oil: 'Authorize this maintenance package.',
        testing: 'Authorize this testing package.',
        'saved-work': 'Creates a new concern. Recommendation status comes from the saved job — change it if needed.',
        evidence: 'Attach a photo, video, or PDF to this repair order.',
        document: 'Scan, upload, or attach existing paperwork for this visit.',
        'hub-document': 'Scan or upload paperwork for this customer.',
        'edit-line': 'Update the common fields for this line.',
        'repair-action': 'What are we doing for this concern?',
        'visit-reason': 'What did the customer say when they brought the vehicle in?',
        'concern-narrative': 'Customer story, findings, and recommendation for this concern.',
        'dragon-service-advisor': 'Rewrite one narrative field. Preview before apply. Nothing changes until you click Apply.',
        'dragon-service-advisor-visit-reason': 'Rewrite the reason for visit. Preview before apply. Nothing changes until you click Apply.',
        'dragon-service-advisor-line-note': 'Rewrite this estimate note. Preview before apply. Nothing changes until you click Apply.',
        'review-estimate-notes': 'Whole-estimate critique with optional rewrite proposals. Nothing changes until you Apply a proposal.',
        'concern-disposition': 'What did the customer decide?',
        'concern-billing': 'How should this scope be billed?',
        'concern-intent': 'How should this recommendation read?',
        'concern-production': 'Where is this approved scope in production?',
        'repair-action-meta': 'Owner, status, and the latest update.',
        'customer-identity': 'Update the customer on this repair order.',
        'vehicle-identity': 'Decode VIN or plate, then review identity and equipment before saving.',
        mileage: 'Mileage in and out for this visit.',
        'visit-posture': 'How did this vehicle arrive for work?',
        'hub-customer': 'Update customer identity and contact details.',
        'hub-vehicle': 'Update this vehicle on the customer record.',
        'hub-vehicle-create': 'Add a vehicle to this customer.',
        'review-request': 'Send a review request by text, email, or both.',
    };

    const primaryLabels = {
        'add-work': 'Continue',
        concern: 'Create Concern',
        labor: 'Add Labor',
        part: 'Add Part',
        sublet: 'Add Sublet',
        note: 'Add Note',
        oil: 'Add Engine Oil Service',
        testing: 'Authorize Testing Package',
        'saved-work': 'Add Work',
        evidence: 'Attach Photo',
        document: '',
        'hub-document': '',
        'edit-line': 'Save Changes',
        'repair-action': 'Add Repair Action',
        'visit-reason': 'Save',
        'concern-narrative': 'Save',
        'dragon-service-advisor': '',
        'dragon-service-advisor-visit-reason': '',
        'dragon-service-advisor-line-note': '',
        'review-estimate-notes': '',
        'concern-disposition': 'Save',
        'concern-billing': 'Save',
        'concern-intent': 'Save',
        'concern-production': 'Save',
        'repair-action-meta': 'Save',
        'customer-identity': 'Save',
        'vehicle-identity': 'Save',
        mileage: 'Save',
        'visit-posture': 'Save',
        'hub-customer': 'Save',
        'hub-vehicle': 'Save',
        'hub-vehicle-create': 'Save Vehicle',
        'review-request': '',
    };

    const bundleTasks = new Set(['repair-action-meta']);
    const bodyActionTasks = new Set([
        'review-request',
        'document',
        'hub-document',
        'dragon-service-advisor',
        'dragon-service-advisor-visit-reason',
        'dragon-service-advisor-line-note',
        'review-estimate-notes',
    ]);

    return {
        open: false,
        task: null,
        context: {},
        invokeEl: null,
        saving: false,
        saved: false,
        validationMessage: '',
        addWorkChoice: null,
        deleteConfirm: false,
        deleteConfirmTimer: null,
        deleteConfirmInterval: null,
        deleteConfirmSeconds: 0,
        canDeleteLine: false,
        initialTask: config.initialTask || null,
        initialContext: config.initialContext || {},

        init() {
            this.$watch('open', (value) => {
                document.body.classList.toggle('overflow-y-hidden', Boolean(value));

                if (value) {
                    this.$nextTick(() => this.focusFirstUsefulInput());
                }
            });

            this._onOpen = (event) => {
                const detail = event.detail || {};

                ensureBuilderTabBeforeModalOpen(() => {
                    this.openTask(
                        detail.task,
                        detail.context || {},
                        detail.invokeEl || document.activeElement,
                    );
                });
            };
            this._onSaved = () => {
                this.saving = false;
                this.saved = true;
            };
            this._onFailed = (event) => {
                this.saving = false;
                this.saved = false;

                const detailMessage = event?.detail?.message;

                if (typeof detailMessage === 'string' && detailMessage.trim() !== '') {
                    this.validationMessage = detailMessage.trim();

                    return;
                }

                if (! this.validationMessage) {
                    this.validationMessage = "Couldn't save. Check the fields above and try again.";
                }
            };
            this._onClose = () => {
                this.close({ force: true });
            };

            window.addEventListener('ark-workspace-modal-open', this._onOpen);
            window.addEventListener('ark-workspace-modal-save-succeeded', this._onSaved);
            window.addEventListener('ark-workspace-modal-save-failed', this._onFailed);
            window.addEventListener('ark-workspace-modal-close', this._onClose);

            this.$el.addEventListener('alpine:destroy', () => {
                window.removeEventListener('ark-workspace-modal-open', this._onOpen);
                window.removeEventListener('ark-workspace-modal-save-succeeded', this._onSaved);
                window.removeEventListener('ark-workspace-modal-save-failed', this._onFailed);
                window.removeEventListener('ark-workspace-modal-close', this._onClose);
                document.body.classList.remove('overflow-y-hidden');
            });

            if (this.initialTask) {
                this.openTask(this.initialTask, this.initialContext, null);
            }
        },

        title() {
            if (this.task === 'edit-line' && this.context.lineLabel) {
                return `Edit ${this.context.lineLabel}`;
            }

            if (this.task === 'review-estimate-notes' && this.context.concernId) {
                return 'Review this concern';
            }

            return titles[this.task] || 'Add Work';
        },

        helper() {
            if (this.task === 'note') {
                const workGroupId = this.context?.workGroupId;

                if (workGroupId != null && workGroupId !== '') {
                    return 'This note will be saved on this Repair Action.';
                }

                return 'This note will be saved on this concern.';
            }

            if (this.task === 'saved-work' && this.context?.concernId) {
                return 'Adds a Repair Action under this concern. Nothing is applied until you add it.';
            }

            if (this.task === 'review-estimate-notes' && this.context.concernId) {
                return 'Critique this concern’s notes. Nothing changes until you Apply a proposal.';
            }

            return helpers[this.task] || '';
        },

        primaryLabel() {
            if (this.saved) {
                return '✓ Saved';
            }

            return primaryLabels[this.task] || 'Save';
        },

        showPrimary() {
            if (! this.task) {
                return false;
            }

            if (bodyActionTasks.has(this.task)) {
                return false;
            }

            if (this.task === 'add-work') {
                return Boolean(this.addWorkChoice);
            }

            if (this.task === 'evidence' && ! this.activeForm()) {
                return false;
            }

            return Boolean(this.primaryLabel());
        },

        showSaveAndRewrite() {
            if (this.saving || this.saved || ! this.showPrimary()) {
                return false;
            }

            if (this.task === 'visit-reason' || this.task === 'concern-narrative' || this.task === 'note') {
                return true;
            }

            return this.isEditingNoteLine();
        },

        isEditingNoteLine() {
            if (this.task !== 'edit-line') {
                return false;
            }

            if (this.context?.lineType === 'note') {
                return true;
            }

            const form = this.activeForm();
            const typeInput = form?.querySelector?.('[name="type"]');

            return typeInput?.value === 'note';
        },

        deleteForm() {
            if (this.task !== 'edit-line') {
                return null;
            }

            const form = this.$refs.dialog?.querySelector('[data-workspace-modal-delete-line]');

            // Do not require panelIsVisible — x-show has not always applied when task
            // flips to edit-line, and DOM visibility is not an Alpine reactive dependency.
            return form instanceof HTMLFormElement ? form : null;
        },

        showDelete() {
            return this.task === 'edit-line' && this.canDeleteLine;
        },

        syncDeleteAvailability() {
            this.canDeleteLine = this.task === 'edit-line'
                && Boolean(this.$refs.dialog?.querySelector('[data-workspace-modal-delete-line]'));
        },

        clearDeleteConfirm() {
            if (this.deleteConfirmTimer) {
                clearTimeout(this.deleteConfirmTimer);
                this.deleteConfirmTimer = null;
            }

            if (this.deleteConfirmInterval) {
                clearInterval(this.deleteConfirmInterval);
                this.deleteConfirmInterval = null;
            }

            this.deleteConfirm = false;
            this.deleteConfirmSeconds = 0;
        },

        deleteLabel() {
            if (! this.deleteConfirm) {
                return 'Delete';
            }

            return `Delete · ${this.deleteConfirmSeconds}`;
        },

        armDelete() {
            if (this.saving || this.saved || ! this.showDelete()) {
                return;
            }

            this.clearDeleteConfirm();
            this.deleteConfirm = true;
            this.deleteConfirmSeconds = 3;

            this.deleteConfirmInterval = setInterval(() => {
                this.deleteConfirmSeconds = Math.max(0, this.deleteConfirmSeconds - 1);

                if (this.deleteConfirmSeconds <= 0 && this.deleteConfirmInterval) {
                    clearInterval(this.deleteConfirmInterval);
                    this.deleteConfirmInterval = null;
                }
            }, 1000);

            this.deleteConfirmTimer = setTimeout(() => {
                this.clearDeleteConfirm();
            }, 3000);
        },

        cancelDelete() {
            this.clearDeleteConfirm();
        },

        async armOrSubmitDelete() {
            if (this.saving || this.saved || ! this.showDelete()) {
                return;
            }

            if (! this.deleteConfirm) {
                this.armDelete();

                return;
            }

            await this.submitDelete();
        },

        async submitDelete() {
            if (this.saving || this.saved || ! this.deleteConfirm) {
                return;
            }

            const form = this.deleteForm();

            if (! (form instanceof HTMLFormElement)) {
                this.clearDeleteConfirm();

                return;
            }

            this.clearDeleteConfirm();
            this.saving = true;
            this.saved = false;

            try {
                const worksheet = this.worksheetRoot();

                if (typeof worksheet?.submitWorksheetForm === 'function') {
                    const ok = await worksheet.submitWorksheetForm(form);

                    if (! ok) {
                        this.saving = false;
                        this.saved = false;

                        return;
                    }

                    this.close({ force: true });

                    return;
                }

                form.requestSubmit();
            } catch {
                this.saving = false;
                this.saved = false;
            }
        },

        openTask(task, context = {}, invokeEl = null) {
            if (! task) {
                return;
            }

            this.saving = false;
            this.saved = false;
            this.validationMessage = '';
            this.clearDeleteConfirm();
            // Add Work: concern is the common path — preselect so Enter on Continue advances.
            this.addWorkChoice = task === 'add-work' ? 'concern' : null;
            this.task = task;
            this.context = { ...context };
            this.invokeEl = invokeEl instanceof HTMLElement ? invokeEl : null;
            this.open = true;

            this.$nextTick(() => {
                this.syncLineCreateForm();
                this.syncDeleteAvailability();
                this.focusFirstUsefulInput();

                // Refs/panels settle after x-show — re-sync type once more so line forms
                // are not submitted with an empty type after a late panel paint.
                this.$nextTick(() => {
                    this.syncLineCreateForm();
                    this.syncDeleteAvailability();

                    if (this.context?.autoGenerate) {
                        const auto = this.context.autoGenerate;
                        delete this.context.autoGenerate;

                        if (auto) {
                            this.triggerDragonGenerate();
                        }
                    }
                });
            });
        },

        syncLineCreateForm() {
            const form = this.lineCreateForm();

            if (! form) {
                return;
            }

            const concernInput = form.querySelector('[name="repair_order_concern_id"]');
            const workGroupInput = form.querySelector('[name="repair_order_work_group_id"]');

            if (concernInput && this.context.concernId != null) {
                concernInput.value = String(this.context.concernId);
            }

            if (workGroupInput) {
                workGroupInput.value = this.context.workGroupId != null
                    ? String(this.context.workGroupId)
                    : '';
            }

            // Fresh key per open — avoid replaying a prior line.store idempotency hit.
            const idempotencyInput = form.querySelector('input[name="worksheet_idempotency_key"]');

            if (idempotencyInput) {
                idempotencyInput.value = '';
            }

            const pricing = window.Alpine?.$data?.(form);

            if (pricing && typeof pricing.selectLineType === 'function' && this.context.lineType) {
                pricing.selectLineType(this.context.lineType);
            } else if (pricing && this.context.lineType) {
                pricing.type = this.context.lineType;
            }
        },

        lineCreateForm() {
            const forms = [...(this.$refs.dialog?.querySelectorAll('[data-workspace-modal-form="line-create"]') ?? [])];
            const concernId = String(this.context?.concernId ?? '');
            const workGroupId = this.context?.workGroupId != null ? String(this.context.workGroupId) : '';

            const matchesContext = (form) => {
                const concernInput = form.querySelector('input[name="repair_order_concern_id"]');
                const workGroupInput = form.querySelector('input[name="repair_order_work_group_id"]');

                if (concernId !== '' && concernInput && String(concernInput.value) !== concernId) {
                    return false;
                }

                if (workGroupId !== '') {
                    return workGroupInput && String(workGroupInput.value) === workGroupId;
                }

                // Scope-level compose (no Repair Action): empty work group id.
                return ! workGroupInput || String(workGroupInput.value) === '';
            };

            const contextual = forms.find((form) => matchesContext(form));

            if (contextual) {
                return contextual;
            }

            return forms.find((form) => {
                const panel = form.closest('.ops-workspace-modal__panel');

                if (! panel) {
                    return false;
                }

                return this.panelIsVisible(panel);
            }) ?? null;
        },

        panelIsVisible(panel) {
            if (! (panel instanceof HTMLElement)) {
                return false;
            }

            // Require the panel itself to be displayed. Do not OR with offsetParent —
            // fixed ancestors make offsetParent null, and children of display:none
            // parents can still report display:block, which falsely marks every
            // concern's Repair Action form as visible (Save then posts the wrong empty form).
            return getComputedStyle(panel).display !== 'none';
        },

        formsInVisiblePanel(selector) {
            return [...(this.$refs.dialog?.querySelectorAll(selector) ?? [])]
                .filter((form) => this.panelIsVisible(form.closest('.ops-workspace-modal__panel')));
        },

        repairActionFormForContext(key) {
            const forms = [...(this.$refs.dialog?.querySelectorAll(`[data-workspace-modal-form="${key}"]`) ?? [])];

            if (forms.length === 0) {
                return null;
            }

            if (key === 'repair-action') {
                const concernId = String(this.context?.concernId ?? '');

                if (concernId !== '') {
                    const matched = forms.find((form) => {
                        const concernInput = form.querySelector('input[name="repair_order_concern_id"]');

                        if (concernInput && String(concernInput.value) === concernId) {
                            return true;
                        }

                        try {
                            return new URL(form.action, window.location.origin).pathname
                                .includes(`/concerns/${concernId}/work-groups`);
                        } catch {
                            return form.action.includes(`/concerns/${concernId}/work-groups`);
                        }
                    });

                    if (matched) {
                        return matched;
                    }
                }
            }

            if (key === 'repair-action-meta') {
                const workGroupId = String(this.context?.workGroupId ?? '');

                if (workGroupId !== '') {
                    const matched = forms.find((form) => {
                        try {
                            const path = new URL(form.action, window.location.origin).pathname;

                            return path.includes(`/work-groups/${workGroupId}/`);
                        } catch {
                            return form.action.includes(`/work-groups/${workGroupId}/`);
                        }
                    });

                    if (matched) {
                        return matched;
                    }
                }
            }

            return this.formsInVisiblePanel(`[data-workspace-modal-form="${key}"]`)[0] ?? null;
        },

        activeForm() {
            const map = {
                concern: 'concern',
                labor: 'line-create',
                part: 'line-create',
                sublet: 'line-create',
                note: 'line-create',
                oil: 'oil',
                testing: 'testing',
                'saved-work': 'saved-work',
                evidence: 'evidence',
                'edit-line': 'edit-line',
                'repair-action': 'repair-action',
                'visit-reason': 'visit-reason',
                'concern-narrative': 'concern-narrative',
                'concern-disposition': 'concern-disposition',
                'concern-billing': 'concern-billing',
                'concern-intent': 'concern-intent',
                'concern-production': 'concern-production',
                'repair-action-meta': 'repair-action-meta',
                'customer-identity': 'customer-identity',
                'vehicle-identity': 'vehicle-identity',
                mileage: 'mileage',
                'visit-posture': 'visit-posture',
                'hub-customer': 'hub-customer',
                'hub-vehicle': 'hub-vehicle',
                'hub-vehicle-create': 'hub-vehicle-create',
            };
            const key = map[this.task];

            if (! key) {
                return null;
            }

            if (key === 'line-create') {
                return this.lineCreateForm();
            }

            if (key === 'repair-action' || key === 'repair-action-meta') {
                return this.repairActionFormForContext(key);
            }

            if (key === 'hub-vehicle') {
                const vehicleId = String(this.context?.vehicleId ?? '');
                const forms = [...(this.$refs.dialog?.querySelectorAll('[data-workspace-modal-form="hub-vehicle"]') ?? [])];
                const matched = forms.find((form) => {
                    try {
                        const path = new URL(form.action, window.location.origin).pathname;

                        return /(^|\/)vehicles\/(\d+)(\/|$)/.test(path)
                            && path.match(/vehicles\/(\d+)/)?.[1] === vehicleId;
                    } catch {
                        return form.action.includes(`/vehicles/${vehicleId}`);
                    }
                });

                return matched
                    ?? this.formsInVisiblePanel('[data-workspace-modal-form="hub-vehicle"]')[0]
                    ?? null;
            }

            // Prefer visible panel; fall back when x-show has not painted yet (identity Save).
            return this.formsInVisiblePanel(`[data-workspace-modal-form="${key}"]`)[0]
                ?? this.$refs.dialog?.querySelector(`[data-workspace-modal-form="${key}"]`)
                ?? null;
        },

        bundleForms() {
            if (! bundleTasks.has(this.task)) {
                const form = this.activeForm();

                return form ? [form] : [];
            }

            return this.formsInVisiblePanel(`[data-workspace-modal-bundle="${this.task}"]`);
        },

        focusables() {
            const root = this.$refs.dialog;

            if (! root) {
                return [];
            }

            const selector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

            return [...root.querySelectorAll(selector)]
                .filter((el) => el.offsetParent !== null || el === document.activeElement);
        },

        focusFirstUsefulInput() {
            if (this.task === 'add-work') {
                // Wait for Continue (x-show) after defaulting addWorkChoice to concern.
                this.$nextTick(() => {
                    const continueBtn = this.$refs.primaryBtn;

                    if (continueBtn instanceof HTMLElement && ! continueBtn.disabled) {
                        continueBtn.focus();
                    }
                });

                return;
            }

            const form = this.activeForm();
            const preferred = form?.querySelector(
                'textarea:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled])',
            );

            if (preferred instanceof HTMLElement) {
                preferred.focus();

                return;
            }

            const first = this.focusables().find((el) => el.getAttribute('aria-label') !== 'Close');

            if (first instanceof HTMLElement) {
                first.focus();
            }
        },

        trapFocus(event) {
            if (! this.open || event.key !== 'Tab') {
                return;
            }

            const items = this.focusables();

            if (items.length === 0) {
                return;
            }

            const first = items[0];
            const last = items[items.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (! event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },

        formIsDirty(form) {
            if (! (form instanceof HTMLFormElement)) {
                return false;
            }

            return [...form.elements].some((el) => {
                if (! (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement || el instanceof HTMLSelectElement)) {
                    return false;
                }

                if (el.type === 'hidden' || el.disabled || el.name === '' || el.name === '_token') {
                    return false;
                }

                if (el.type === 'checkbox' || el.type === 'radio') {
                    return el.checked !== el.defaultChecked;
                }

                return el.value !== el.defaultValue;
            });
        },

        requestClose() {
            if (this.saving) {
                return;
            }

            if (this.task === 'review-request' && this.context.closePaid) {
                window.dispatchEvent(new CustomEvent('ark-review-request-dismissed', {
                    detail: {
                        lifecycleFormId: this.context.lifecycleFormId || null,
                    },
                }));
            }

            // Live authoring: close discards drafts. Save is an explicit primary action.
            this.close({ force: true });
        },

        async submitWorksheetForm(eventOrForm) {
            const form = eventOrForm instanceof HTMLFormElement
                ? eventOrForm
                : (eventOrForm?.target?.closest?.('form') ?? eventOrForm?.currentTarget ?? null);

            if (! (form instanceof HTMLFormElement) || this.saving) {
                return false;
            }

            this.saving = true;
            this.saved = false;

            try {
                const worksheet = this.worksheetRoot();

                if (typeof worksheet?.submitWorksheetForm === 'function') {
                    const ok = await worksheet.submitWorksheetForm(form);

                    if (! ok) {
                        this.saving = false;
                        this.saved = false;

                        return false;
                    }

                    return true;
                }

                form.requestSubmit();

                return true;
            } catch {
                this.saving = false;
                this.saved = false;

                return false;
            }
        },

        close({ force = false } = {}) {
            if ((this.saving || this.saved) && ! force) {
                return;
            }

            this.open = false;
            this.clearDeleteConfirm();
            this.canDeleteLine = false;
            this.task = null;
            this.context = {};
            this.saving = false;
            this.saved = false;
            this.validationMessage = '';
            this.addWorkChoice = null;
            document.body.classList.remove('overflow-y-hidden');

            const invokeEl = this.invokeEl;
            this.invokeEl = null;

            this.$nextTick(() => {
                if (invokeEl instanceof HTMLElement && document.contains(invokeEl)) {
                    invokeEl.focus();
                }
            });
        },

        onEscape() {
            if (! this.open) {
                return;
            }

            this.requestClose();
        },

        onBackdrop() {
            this.requestClose();
        },

        chooseAddWork(option) {
            const map = {
                concern: 'concern',
                oil: 'oil',
                testing: 'testing',
                'saved-work': 'saved-work',
            };
            const next = map[option];

            if (! next) {
                return;
            }

            this.openTask(next, {}, this.invokeEl);
        },

        continueAddWork() {
            if (! this.addWorkChoice) {
                return;
            }

            this.chooseAddWork(this.addWorkChoice);
        },

        worksheetRoot() {
            return this.$el.closest('[data-worksheet-root]')?._x_dataStack?.[0] ?? null;
        },

        formIsReadyToSubmit(form) {
            if (! (form instanceof HTMLFormElement)) {
                return false;
            }

            if (this.task === 'saved-work') {
                const panel = form.closest('[x-data]');
                const picker = window.Alpine?.$data?.(panel);

                if (! picker?.selectedId) {
                    this.validationMessage = 'Select a Common Job to add.';

                    return false;
                }

                if (picker?.recall?.requires_review && ! picker?.laborConfirmed) {
                    this.validationMessage = 'Confirm suggested hours from shop history, or clear the suggestion.';

                    return false;
                }
            }

            const pricing = window.Alpine?.$data?.(form);

            if (pricing?.type === 'labor' && pricing.laborCategoryAllowsModifiers?.() && pricing.sellEdited) {
                const reason = String(pricing.laborRateOverrideReason ?? '').trim();

                if (reason === '') {
                    this.validationMessage = 'Custom labor rate needs a reason — choose Menu / package price for a flat PPI, or another reason.';
                    this.$nextTick(() => {
                        form.querySelector('[name="labor_rate_override_reason"]')?.focus();
                    });

                    return false;
                }
            }

            if (pricing?.type === 'labor' && pricing.laborCategoryAllowsModifiers?.() && pricing.laborHoursOverridden) {
                const hoursReason = String(pricing.laborOverrideReason ?? '').trim();

                if (hoursReason === '') {
                    this.validationMessage = 'Custom billable hours need a short reason before saving.';
                    this.$nextTick(() => {
                        form.querySelector('[name="labor_override_reason"]')?.focus();
                    });

                    return false;
                }
            }

            if (! form.checkValidity()) {
                form.reportValidity();
                this.validationMessage = 'Fill the required fields above, then save.';

                return false;
            }

            return true;
        },

        async submitPrimary() {
            if (this.saving || this.saved || ! this.showPrimary()) {
                return;
            }

            if (this.task === 'add-work') {
                this.continueAddWork();

                return;
            }

            if (bundleTasks.has(this.task)) {
                await this.submitBundle();

                return;
            }

            const form = this.activeForm();

            if (! (form instanceof HTMLFormElement)) {
                this.validationMessage = 'Form is not ready yet. Close this panel, open it again, then retry.';

                return;
            }

            this.validationMessage = '';

            if (! this.formIsReadyToSubmit(form)) {
                return;
            }

            this.saving = true;
            this.saved = false;

            try {
                const pricing = window.Alpine?.$data?.(form);

                if (typeof pricing?.submitLine === 'function') {
                    if (! pricing.formHasLineType?.(form) && ! pricing.hasLineType?.()) {
                        this.saving = false;
                        this.validationMessage = 'Choose a line type before saving.';

                        return;
                    }

                    await pricing.submitLine(form);

                    return;
                }

                const worksheet = this.worksheetRoot();

                if (typeof worksheet?.submitWorksheetForm === 'function') {
                    const ok = await worksheet.submitWorksheetForm(form);

                    if (ok === false || ok?.ok === false) {
                        this.saving = false;
                        this.saved = false;

                        return;
                    }

                    // Continuity flashes ✓ Saved then refreshes panels. Close here so a
                    // stalled morph cannot leave an undismissable overlay (oil/testing).
                    this.close({ force: true });

                    return;
                }

                form.requestSubmit();
            } catch {
                this.saving = false;
                this.saved = false;
            }
        },

        async submitPrimaryAndRewrite() {
            if (! this.showSaveAndRewrite()) {
                return;
            }

            const form = this.activeForm();

            if (! (form instanceof HTMLFormElement)) {
                this.validationMessage = 'Form is not ready yet. Close this panel, open it again, then retry.';

                return;
            }

            this.validationMessage = '';

            if (! this.formIsReadyToSubmit(form)) {
                return;
            }

            if (! this.formHasRewriteableText(form)) {
                this.validationMessage = 'Add text first, then Save & Generate.';

                return;
            }

            const pendingDragonRewrite = this.buildPendingDragonRewrite(form);

            if (! pendingDragonRewrite) {
                this.validationMessage = 'Could not start Generate from this panel.';

                return;
            }

            this.saving = true;
            this.saved = false;

            try {
                const worksheet = this.worksheetRoot();

                if (typeof worksheet?.submitWorksheetForm !== 'function') {
                    this.saving = false;
                    this.validationMessage = 'Could not save. Try Save, then Generate.';

                    return;
                }

                // Worksheet morph replaces #workspace-modal-host — handoff reopens Dragon after refresh.
                const result = await worksheet.submitWorksheetForm(form, {
                    keepModalOpen: true,
                    pendingDragonRewrite,
                });

                if (result === false || result?.ok === false) {
                    this.saving = false;
                    this.saved = false;
                }
            } catch {
                this.saving = false;
                this.saved = false;
            }
        },

        buildPendingDragonRewrite(form) {
            if (this.task === 'visit-reason') {
                return { kind: 'visit-reason' };
            }

            if (this.task === 'concern-narrative') {
                return {
                    kind: 'concern-narrative',
                    concernId: this.context.concernId,
                    field: this.capturePreferredNarrativeField(form),
                };
            }

            if (this.task === 'note' || this.isEditingNoteLine()) {
                return {
                    kind: 'line-note',
                    lineId: this.editingLineIdFromForm(form) || this.context.lineId || null,
                };
            }

            return null;
        },

        formHasRewriteableText(form) {
            if (this.task === 'visit-reason') {
                return String(form.querySelector('[name="visit_reason"]')?.value || '').trim() !== '';
            }

            if (this.task === 'note' || this.isEditingNoteLine()) {
                return String(form.querySelector('[name="description"]')?.value || '').trim() !== '';
            }

            if (this.task === 'concern-narrative') {
                return ['verified_findings', 'customer_states', 'recommendation', 'dtcs_summary', 'summary']
                    .some((name) => String(form.querySelector(`[name="${name}"]`)?.value || '').trim() !== '');
            }

            return true;
        },

        capturePreferredNarrativeField(form) {
            const order = ['verified_findings', 'customer_states', 'recommendation', 'dtcs_summary', 'summary'];

            for (const name of order) {
                if (String(form.querySelector(`[name="${name}"]`)?.value || '').trim() !== '') {
                    return name;
                }
            }

            return 'verified_findings';
        },

        editingLineIdFromForm(form) {
            const match = String(form?.action || '').match(/\/lines\/(\d+)(?:\/|$)/);

            return match?.[1] || this.context.lineId || null;
        },

        triggerDragonGenerate() {
            const dialog = this.$refs.dialog;

            if (! dialog) {
                return;
            }

            const panels = dialog.querySelectorAll('.ops-workspace-modal__panel');

            for (const panel of panels) {
                if (panel.style.display === 'none') {
                    continue;
                }

                const data = window.Alpine?.$data?.(panel);

                if (! data || typeof data.generate !== 'function') {
                    continue;
                }

                if (typeof data.syncFieldFromContext === 'function') {
                    data.syncFieldFromContext(this);
                } else {
                    if (this.context.lineId != null && data.lineId !== undefined) {
                        data.lineId = this.context.lineId;
                    }

                    if (this.context.field && data.field !== undefined) {
                        data.field = this.context.field;
                    }
                }

                data.generate();

                return;
            }
        },

        async submitBundle() {
            const forms = this.bundleForms().filter((form) => this.formIsDirty(form));
            const worksheet = this.worksheetRoot();

            if (forms.length === 0) {
                this.close({ force: true });

                return;
            }

            if (typeof worksheet?.submitWorksheetForm !== 'function') {
                forms[0]?.requestSubmit();

                return;
            }

            this.saving = true;
            this.saved = false;

            try {
                for (let index = 0; index < forms.length; index += 1) {
                    const form = forms[index];
                    const isLast = index === forms.length - 1;
                    const result = await worksheet.submitWorksheetForm(form, {
                        deferRefresh: ! isLast,
                        acknowledgeModal: isLast,
                    });

                    if (result === false || result?.ok === false) {
                        this.saving = false;
                        this.saved = false;

                        return;
                    }
                }
            } catch {
                this.saving = false;
                this.saved = false;
            }
        },
    };
}

export function openWorkspaceModal(task, context = {}, invokeEl = null) {
    window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', {
        detail: { task, context, invokeEl },
    }));
}
