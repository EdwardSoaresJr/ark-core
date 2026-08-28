const WORKSHEET_FETCH_INIT = {
    credentials: 'same-origin',
    cache: 'no-store',
};

function uniqueIds(ids) {
    return [...new Set(ids.filter(Boolean))];
}

function filledString(value) {
    return typeof value === 'string' && value.trim() !== '';
}

export const arkWorksheetContinuity = (config = {}) => {
    const worksheetScopeId = config.worksheetScopeId ?? 'estimate-lines';
    const continuityPanelIds = uniqueIds([
        ...(config.continuityPanelIds ?? ['estimate-total-panel']),
        // Totals authority must refresh even when a parent rail id is omitted/misconfigured.
        'estimate-total-panel',
    ]);
    const conflictFragment = config.conflictFragment ?? worksheetScopeId;
    const refreshScopeMap = {
        worksheet: worksheetScopeId,
        ...(config.refreshScopeMap ?? {}),
    };
    const workspaceTabReloadMap = config.workspaceTabReloadMap ?? {};

    const worksheetBusyShowDelayMs = Number(config.worksheetBusyShowDelayMs ?? 120);

    return {
        worksheetBusyDepth: 0,
        worksheetSaving: false,
        worksheetBusyPending: false,
        worksheetBusyShowTimer: null,
        worksheetBusyShowDelayMs,
        worksheetSavingLabel: 'Saving…',
        worksheetScopeId,
        continuityPanelIds,
        conflictFragment,
        refreshScopeMap,
        workspaceTabReloadMap,
        focusableSelector: 'a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
        worksheetIdempotencyField: 'worksheet_idempotency_key',

        resolveRefreshScope(scope) {
            return this.refreshScopeMap[scope] ?? this.worksheetScopeId;
        },

        scopedWorksheetTarget(anchor) {
            if (anchor?.dataset?.refreshScope) {
                return document.getElementById(this.resolveRefreshScope(anchor.dataset.refreshScope));
            }

            return anchor?.closest?.('[id^=concern-], [id^=line-]')
                || document.getElementById(this.worksheetScopeId);
        },

        scrollAnchor(formOrAnchor) {
            const form = formOrAnchor instanceof HTMLFormElement ? formOrAnchor : null;
            const anchor = form ?? formOrAnchor;
            const focusSelector = form?.dataset?.continuityFocus?.trim();

            if (focusSelector) {
                const focusElement = document.querySelector(focusSelector);
                const lineOrConcern = focusElement?.closest?.('[id^=line-], [id^=concern-]');

                if (lineOrConcern) {
                    return lineOrConcern;
                }

                if (focusElement?.id) {
                    return focusElement;
                }
            }

            if (form?.dataset?.refreshScope) {
                return document.getElementById(this.resolveRefreshScope(form.dataset.refreshScope));
            }

            return anchor?.closest?.('[id^=line-], [id^=concern-]')
                || document.getElementById(this.worksheetScopeId);
        },

        focusTargetFrom(anchor) {
            const active = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            const explicitSelector = anchor?.dataset.continuityFocus;

            if (explicitSelector) {
                return {
                    selector: explicitSelector,
                };
            }

            if (! active || ! anchor?.contains(active)) {
                return null;
            }

            if (active.id) {
                return {
                    selector: `#${CSS.escape(active.id)}`,
                };
            }

            if (active.name) {
                const scope = anchor.id ? `#${CSS.escape(anchor.id)} ` : '';

                return {
                    selector: `${scope}[name='${CSS.escape(active.name)}']`,
                    selectionStart: typeof active.selectionStart === 'number' ? active.selectionStart : null,
                    selectionEnd: typeof active.selectionEnd === 'number' ? active.selectionEnd : null,
                };
            }

            return null;
        },

        clearLineEditQueryParam() {
            const url = new URL(window.location.href);

            if (! url.searchParams.has('editing_line')) {
                return;
            }

            url.searchParams.delete('editing_line');
            window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}${url.hash}`);
        },

        isLineUpdateForm(form) {
            return form instanceof HTMLFormElement
                && form.id?.startsWith('line-update-');
        },

        isLineStoreForm(form) {
            return form instanceof HTMLFormElement
                && (
                    form.id?.startsWith('line-store-concern-')
                    || form.id?.startsWith('line-store-work-group-')
                    || form.id?.startsWith('workspace-line-create-')
                    || form.dataset?.workspaceModalForm === 'line-create'
                );
        },

        submittedLineStoreType(form) {
            const state = form?._x_dataStack?.[0];

            if (state?.type) {
                return state.type;
            }

            return form?.querySelector('input[name="type"]')?.value?.trim() ?? '';
        },

        collapseLineStoreCompose(formId, submittedType = '') {
            if (! submittedType) {
                return;
            }

            const storeForm = document.getElementById(formId);

            if (! storeForm) {
                return;
            }

            const state = storeForm._x_dataStack?.[0];

            if (! state || state.type !== submittedType) {
                return;
            }

            window.clearTimeout(state.previewTimer);
            state.previewSequence = (state.previewSequence ?? 0) + 1;
            state.type = '';
            state.cost = '';
            state.sell = '';
            state.sellEdited = false;
            state.explicitMatrix = false;
            state.guidance = '';
            state.marginPercentage = null;
            state.matrixMarginPercentage = null;
            state.markupPercentage = null;
            state.previewing = false;

            if (state.defaultMatrixKey) {
                state.matrixKey = state.defaultMatrixKey;
                state.pricingMode = 'matrix';
                state.pricingSelection = state.defaultMatrixKey;
            }

            const typeInput = storeForm.querySelector('input[name="type"]');

            if (typeInput) {
                typeInput.value = '';
            }
        },

        restoreFocus(focusTarget) {
            if (! focusTarget?.selector) {
                return;
            }

            requestAnimationFrame(() => {
                const target = document.querySelector(focusTarget.selector);

                if (! target || ! target.matches(this.focusableSelector)) {
                    return;
                }

                target.focus({ preventScroll: true });

                if (
                    typeof focusTarget.selectionStart === 'number'
                    && typeof focusTarget.selectionEnd === 'number'
                    && typeof target.setSelectionRange === 'function'
                ) {
                    target.setSelectionRange(focusTarget.selectionStart, focusTarget.selectionEnd);
                }
            });
        },

        restoreOpenState(anchor) {
            const detailsSelector = anchor?.dataset.continuityDetails;

            if (! detailsSelector) {
                return;
            }

            requestAnimationFrame(() => {
                const details = document.querySelector(detailsSelector);

                if (details) {
                    details.open = true;
                }
            });
        },

        restoreAnchor(anchorId, anchorTop) {
            requestAnimationFrame(() => {
                const restoredAnchor = anchorId ? document.getElementById(anchorId) : null;

                if (! restoredAnchor || anchorTop === undefined) {
                    return;
                }

                window.scrollBy({
                    top: restoredAnchor.getBoundingClientRect().top - anchorTop,
                    left: 0,
                    behavior: 'instant',
                });
            });
        },

        beginWorksheetBusy(formOrLabel = null) {
            this.worksheetSavingLabel = this.resolveWorksheetSavingLabel(formOrLabel);
            this.worksheetBusyDepth = (this.worksheetBusyDepth ?? 0) + 1;
            this.worksheetBusyPending = true;

            if (this.worksheetSaving || this.worksheetBusyShowTimer !== null) {
                return;
            }

            this.worksheetBusyShowTimer = window.setTimeout(() => {
                this.worksheetBusyShowTimer = null;

                if (this.worksheetBusyPending) {
                    this.worksheetSaving = true;
                }
            }, this.worksheetBusyShowDelayMs);
        },

        resolveWorksheetSavingLabel(formOrLabel) {
            if (typeof formOrLabel === 'string' && formOrLabel.trim() !== '') {
                return formOrLabel.trim();
            }

            if (formOrLabel instanceof HTMLFormElement) {
                return formOrLabel.dataset.savingLabel?.trim() || 'Saving…';
            }

            return 'Saving…';
        },

        ensureWorksheetIdempotencyKey(form) {
            if (! (form instanceof HTMLFormElement)) {
                return null;
            }

            let input = form.querySelector(`input[name="${this.worksheetIdempotencyField}"]`);

            if (! input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = this.worksheetIdempotencyField;
                form.appendChild(input);
            }

            if (! input.value) {
                input.value = (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function')
                    ? crypto.randomUUID()
                    : `ws-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
            }

            return input.value;
        },

        clearWorksheetIdempotencyKey(form) {
            if (! (form instanceof HTMLFormElement)) {
                return;
            }

            const input = form.querySelector(`input[name="${this.worksheetIdempotencyField}"]`);

            if (input) {
                input.value = '';
            }
        },

        surfaceWorksheetSaved() {
            const flash = document.getElementById('worksheet-status-flash');
            const serverStatus = flash?.querySelector('[data-worksheet-server-status]')?.textContent?.trim()
                || flash?.textContent?.trim()
                || '';

            if (serverStatus.toLowerCase().includes('saved') || serverStatus.toLowerCase().includes("couldn't save")) {
                this.revealWorksheetStatus();

                return;
            }

            this.surfaceWorksheetMessage('Saved', { tone: 'success' });
        },

        endWorksheetBusy() {
            this.worksheetBusyDepth = Math.max(0, (this.worksheetBusyDepth ?? 1) - 1);

            if (this.worksheetBusyDepth > 0) {
                return;
            }

            this.worksheetBusyPending = false;

            if (this.worksheetBusyShowTimer !== null) {
                window.clearTimeout(this.worksheetBusyShowTimer);
                this.worksheetBusyShowTimer = null;
            }

            this.worksheetSaving = false;
            this.worksheetSavingLabel = 'Saving…';
        },

        revealWorksheetStatus() {
            requestAnimationFrame(() => {
                focusWorksheetConcernRepairAction();

                const flash = document.getElementById('worksheet-status-flash');

                if (! flash || flash.textContent.trim() === '') {
                    return;
                }

                flash.classList.remove('ops-worksheet-status-flash--pulse');
                void flash.offsetWidth;
                flash.classList.add('ops-worksheet-status-flash--pulse');
            });
        },

        async refreshScope(scope) {
            if (this.worksheetBusyPending) {
                return;
            }

            const anchor = document.createElement('span');
            anchor.dataset.refreshScope = scope;

            const url = new URL(window.location.href);
            url.hash = '';

            await this.refreshWorksheet(url.toString(), anchor);
        },

        surfaceWorksheetMessage(message, { tone = 'warn' } = {}) {
            const flash = document.getElementById('worksheet-status-flash');

            if (! flash || ! message) {
                return;
            }

            let notice = flash.querySelector('[data-worksheet-client-notice]');

            if (! notice) {
                notice = document.createElement('div');
                notice.dataset.worksheetClientNotice = '1';
                flash.prepend(notice);
            }

            notice.className = tone === 'success'
                ? 'border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-950'
                : tone === 'error'
                    ? 'border border-rose-300 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-950'
                    : 'border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-950';
            notice.textContent = message;
            flash.hidden = false;
            flash.classList.remove('ops-worksheet-status-flash--pulse');
            void flash.offsetWidth;
            flash.classList.add('ops-worksheet-status-flash--pulse');
        },

        replaceFromDocument(doc, anchor) {
            const scopedTarget = anchor?.dataset.refreshScope
                ? document.getElementById(this.resolveRefreshScope(anchor.dataset.refreshScope))
                : this.scopedWorksheetTarget(anchor);
            const ids = uniqueIds([
                scopedTarget?.id,
                ...this.continuityPanelIds,
                'estimate-total-panel',
            ]);
            const missing = [];
            let replaced = 0;

            ids.forEach((id) => {
                const current = document.getElementById(id);
                const fresh = doc.getElementById(id);

                if (! current || ! fresh) {
                    if (current && ! fresh) {
                        missing.push(id);
                    }

                    return;
                }

                try {
                    if (window.Alpine?.destroyTree) {
                        window.Alpine.destroyTree(current);
                    }

                    // Clone so nested continuity ids (e.g. estimate-total-panel inside
                    // estimate-builder-rail) remain available in `doc` for later replaces.
                    // replaceWith() would otherwise move the node out of the parsed document.
                    const next = fresh.cloneNode(true);
                    current.replaceWith(next);
                    window.Alpine?.initTree?.(next);
                    replaced += 1;
                } catch (error) {
                    console.error('[ARK worksheet] Failed to replace continuity panel', id, error);
                    missing.push(id);
                }
            });

            // DOM baselines reset — sticky dirty from before refresh must re-check forms.
            if (typeof window.ARK?.workspace?.syncDirty === 'function') {
                window.ARK.workspace.syncDirty();
            } else {
                window.ARK?.workspace?.setDirty?.(false);
            }

            const totalsMissing = missing.includes('estimate-builder-rail')
                || (
                    missing.includes('estimate-total-panel')
                    && ! document.querySelector('#estimate-builder-rail #estimate-total-panel')
                );

            if (totalsMissing) {
                this.surfaceWorksheetMessage('Totals could not refresh. Reload the page, then retry if needed.');
            }

            return { replaced, missing };
        },

        reopenConcernStoreIfNeeded(doc) {
            const hasConcernErrors = Boolean(
                doc.querySelector('#concern-store .ops-field-error, #concern-store [data-concern-error], [data-workspace-modal-form="concern"] .ops-field-error'),
            );

            if (hasConcernErrors) {
                window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', {
                    detail: { task: 'concern' },
                }));

                return;
            }

            const host = doc.getElementById('workspace-modal-host');
            const reopen = host?.getAttribute('data-workspace-modal-reopen');

            if (reopen) {
                window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', {
                    detail: { task: reopen },
                }));
            }
        },

        applyWorksheetHtml(doc, form, {
            anchorId = null,
            anchorTop = undefined,
            focusTarget = null,
            lineStoreSubmission = null,
        } = {}) {
            this.replaceFromDocument(doc, form);
            this.syncEstimateVersion(doc);
            this.markEstimateRendered(this.openedEstimateVersion);
            this.clearStaleNotice();

            if (this.isLineUpdateForm(form)) {
                this.clearLineEditQueryParam();
            }

            if (lineStoreSubmission) {
                this.collapseLineStoreCompose(
                    lineStoreSubmission.formId,
                    lineStoreSubmission.submittedType,
                );
            }

            this.restoreOpenState(form);
            this.restoreAnchor(anchorId, anchorTop);
            this.restoreFocus(focusTarget);
            this.reopenConcernStoreIfNeeded(doc);
            window.ARK?.workspace?.setDirty?.(false);
            this.surfaceWorksheetSaved();
        },

        async refreshWorksheet(url, anchor) {
            const scrollAnchor = anchor instanceof HTMLFormElement
                ? this.scrollAnchor(anchor)
                : this.scopedWorksheetTarget(anchor);
            const anchorId = scrollAnchor?.id;
            const anchorTop = scrollAnchor?.getBoundingClientRect().top;
            const focusTarget = this.focusTargetFrom(anchor);
            const refreshScope = anchor instanceof HTMLFormElement
                ? anchor.dataset?.refreshScope
                : anchor?.dataset?.refreshScope;
            const workspaceTab = refreshScope ? workspaceTabReloadMap[refreshScope] : null;

            if (document.activeElement instanceof HTMLElement) {
                document.activeElement.blur();
            }

            this.beginWorksheetBusy();

            try {
                if (
                    workspaceTab
                    && typeof window.arkReloadRepairOrderWorkspaceTab === 'function'
                    && document.querySelector(`[data-workspace-tab-panel="${workspaceTab}"]`)
                ) {
                    await window.arkReloadRepairOrderWorkspaceTab(workspaceTab);

                    if (anchorId && anchorTop !== undefined) {
                        requestAnimationFrame(() => {
                            const restored = document.getElementById(anchorId);

                            if (! restored) {
                                return;
                            }

                            const nextTop = restored.getBoundingClientRect().top;
                            window.scrollBy({ top: nextTop - anchorTop, left: 0 });
                        });
                    }

                    this.restoreFocus(focusTarget);
                    this.revealWorksheetStatus();

                    return;
                }

                const response = await fetch(url, {
                    ...WORKSHEET_FETCH_INIT,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'text/html',
                    },
                });

                if (! response.ok) {
                    window.location.href = url;

                    return;
                }

                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');

                this.replaceFromDocument(doc, anchor);
                this.syncEstimateVersion(doc);
                this.clearStaleNotice();
                this.restoreOpenState(anchor);
                this.restoreAnchor(anchorId, anchorTop);
                this.restoreFocus(focusTarget);
                this.revealWorksheetStatus();
            } catch {
                window.location.href = url;
            } finally {
                this.endWorksheetBusy();
            }
        },

        async editLine(event) {
            event.preventDefault();
            const link = event.currentTarget;

            // editing_line refreshes the worksheet; host opens modal authoring for that line.
            // Compatibility debt — keep until edit URLs stop being bookmarked.
            link.dataset.refreshScope = 'worksheet';

            const targetUrl = new URL(link.href, window.location.origin);
            const closingEdit = ! targetUrl.searchParams.has('editing_line');

            if (closingEdit) {
                window.ARK?.workspace?.setDirty?.(false);
            }

            await this.refreshWorksheet(link.href, link);

            if (closingEdit) {
                this.clearLineEditQueryParam();
                window.ARK?.roWorkspaceMemory?.persist?.();
            }
        },

        async worksheetModalErrorMessage(response) {
            try {
                const clone = response.clone();
                const contentType = clone.headers.get('content-type') ?? '';

                if (contentType.includes('application/json')) {
                    const payload = await clone.json();
                    const firstError = payload?.errors
                        ? Object.values(payload.errors).flat().find((message) => filledString(message))
                        : null;

                    return firstError || (filledString(payload?.message) ? payload.message : null);
                }

                const text = (await clone.text()).trim();

                if (text && text.length < 240 && ! text.startsWith('<')) {
                    return text;
                }
            } catch {
                // Fall through to generic copy.
            }

            return null;
        },

        worksheetModalHasRenderedError(doc) {
            if (! (doc instanceof Document)) {
                return false;
            }

            // Color classes are not validation. Dragon rewrite slots use text-rose-700 while
            // empty (x-show="errorMessage"); a color query always "fails" a successful save.
            const selectors = [
                '#workspace-modal-host .ops-field-error',
                '#workspace-modal-host [data-concern-error]',
                '#workspace-modal-host [data-review-request-error]',
                '#workspace-modal-host [data-workspace-modal-validation]',
                '#concern-store .ops-field-error',
                '#concern-store [data-concern-error]',
            ];

            return selectors.some((selector) => {
                const el = doc.querySelector(selector);

                return Boolean(el?.textContent?.trim());
            });
        },

        worksheetModalSaveLooksFailed(doc, form = null) {
            if (! (doc instanceof Document)) {
                return false;
            }

            if (this.worksheetModalHasRenderedError(doc)) {
                return true;
            }

            const modalForm = form?.dataset?.workspaceModalForm;
            const status = doc.querySelector('[data-worksheet-server-status]')?.textContent?.trim() || '';
            const createdLineId = doc.querySelector('[data-worksheet-root][data-ark-line-id]')
                ?.getAttribute('data-ark-line-id');

            if (modalForm === 'line-create' || modalForm === 'edit-line') {
                if (createdLineId) {
                    return false;
                }

                return ! /saved/i.test(status);
            }

            if (modalForm === 'repair-action') {
                return ! /repair added/i.test(status);
            }

            return false;
        },

        async acknowledgeWorkspaceModalSave(form, doc, options = {}) {
            const keepOpen = options.keepOpen === true;

            if (! form?.hasAttribute?.('data-workspace-modal-form')) {
                return true;
            }

            if (this.worksheetModalSaveLooksFailed(doc, form)) {
                window.dispatchEvent(new CustomEvent('ark-workspace-modal-save-failed'));

                return false;
            }

            window.dispatchEvent(new CustomEvent('ark-workspace-modal-save-succeeded'));
            await new Promise((resolve) => setTimeout(resolve, 180));

            if (! keepOpen) {
                // Unlock before panel morph — never leave ✓ Saved with Close disabled forever.
                window.dispatchEvent(new CustomEvent('ark-workspace-modal-close'));
            }

            return true;
        },

        async submitWorksheetForm(eventOrForm, options = {}) {
            const deferRefresh = options.deferRefresh === true;
            const acknowledgeModal = options.acknowledgeModal !== false;
            const keepModalOpen = options.keepModalOpen === true;
            const pendingDragonRewrite = options.pendingDragonRewrite || null;

            const form = eventOrForm instanceof HTMLFormElement
                ? eventOrForm
                : (eventOrForm?.target?.closest?.('form') ?? eventOrForm?.currentTarget ?? null);

            if (! form) {
                return false;
            }

            // Claim the busy gate before any await so double-clicks cannot race two POSTs.
            if (this.worksheetBusyPending) {
                this.surfaceWorksheetMessage('Still saving…', { tone: 'warn' });

                return false;
            }

            this.worksheetBusyPending = true;
            this._pendingDragonRewrite = pendingDragonRewrite;

            const anchor = this.scrollAnchor(form);
            const anchorId = anchor?.id;
            const anchorTop = anchor?.getBoundingClientRect().top;
            const focusTarget = this.focusTargetFrom(form) || this.focusTargetFrom(anchor);
            const lineStoreSubmission = this.isLineStoreForm(form)
                ? {
                    formId: form.id,
                    submittedType: this.submittedLineStoreType(form),
                }
                : null;

            if (document.activeElement instanceof HTMLElement) {
                document.activeElement.blur();
            }

            this.ensureWorksheetIdempotencyKey(form);
            this.beginWorksheetBusy(form);

            try {
                const body = new FormData(form);
                const versionField = this.estimateVersionField ?? 'opened_estimate_version';

                if (this.openedEstimateVersion) {
                    body.set(versionField, String(this.openedEstimateVersion));
                }

                const response = await fetch(form.action, {
                    ...WORKSHEET_FETCH_INIT,
                    method: 'POST',
                    body,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'text/html',
                    },
                });

                const createdLineId = response.headers.get('X-ARK-Line-Id')
                    || null;

                const resolveLineId = (doc = null) => (
                    createdLineId
                    || doc?.querySelector?.('[data-worksheet-root][data-ark-line-id]')?.getAttribute('data-ark-line-id')
                    || null
                );

                if (response.status === 409) {
                    this._pendingDragonRewrite = null;
                    await this.applyWorksheetConflict(response);
                    this.surfaceWorksheetMessage("Couldn't save. Retry", { tone: 'error' });
                    window.dispatchEvent(new CustomEvent('ark-workspace-modal-save-failed'));

                    return false;
                }

                if (! response.ok) {
                    this._pendingDragonRewrite = null;
                    const modalError = await this.worksheetModalErrorMessage(response);
                    this.surfaceWorksheetMessage(modalError || "Couldn't save. Retry", { tone: 'error' });
                    window.dispatchEvent(new CustomEvent('ark-workspace-modal-save-failed', {
                        detail: { message: modalError || '' },
                    }));
                    // Never native-submit modal forms on AJAX failure — form.submit()
                    // bypasses HTML5 required checks and re-posts the same empty/wrong form.

                    return false;
                }

                const contentType = response.headers.get('content-type') ?? '';

                if (contentType.includes('application/json')) {
                    await response.json();
                    this.clearWorksheetIdempotencyKey(form);

                    if (deferRefresh) {
                        return { ok: true, doc: null, lineId: resolveLineId() };
                    }

                    if (acknowledgeModal && form.hasAttribute('data-workspace-modal-form')) {
                        window.dispatchEvent(new CustomEvent('ark-workspace-modal-save-succeeded'));
                        await new Promise((resolve) => setTimeout(resolve, 180));
                        if (! keepModalOpen) {
                            window.dispatchEvent(new CustomEvent('ark-workspace-modal-close'));
                        }
                    }

                    await this.refreshWorksheet(window.location.href, form);
                    window.ARK?.workspace?.setDirty?.(false);
                    this.surfaceWorksheetSaved();
                    this.reopenPendingDragonRewrite(null, resolveLineId());

                    return { ok: true, lineId: resolveLineId() };
                }

                // Redirected POSTs already followed to the canonical Builder HTML (flash/errors
                // included). Apply that body — do not re-GET, which drops flash and can serve
                // a cached pre-mutation page (stale totals). Canonical projections replace UI.
                const html = await response.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');

                this.syncEstimateVersion(doc);

                if (deferRefresh) {
                    this.clearWorksheetIdempotencyKey(form);

                    return { ok: true, doc, lineId: resolveLineId(doc) };
                }

                if (acknowledgeModal) {
                    const acknowledged = await this.acknowledgeWorkspaceModalSave(form, doc, {
                        keepOpen: keepModalOpen,
                    });

                    if (! acknowledged) {
                        this._pendingDragonRewrite = null;

                        return false;
                    }
                }

                this.clearWorksheetIdempotencyKey(form);

                this.applyWorksheetHtml(doc, form, {
                    anchorId,
                    anchorTop,
                    focusTarget,
                    lineStoreSubmission,
                });

                const lineId = resolveLineId(doc);
                this.reopenPendingDragonRewrite(doc, lineId);

                return { ok: true, lineId };
            } catch {
                this._pendingDragonRewrite = null;
                this.surfaceWorksheetMessage("Couldn't save. Retry", { tone: 'error' });
                window.dispatchEvent(new CustomEvent('ark-workspace-modal-save-failed'));

                return false;
            } finally {
                this.endWorksheetBusy();
            }
        },

        reopenPendingDragonRewrite(doc, lineId = null) {
            const pending = this._pendingDragonRewrite;
            this._pendingDragonRewrite = null;

            if (! pending) {
                return;
            }

            const resolvedLineId = lineId
                || pending.lineId
                || doc?.querySelector?.('[data-worksheet-root][data-ark-line-id]')?.getAttribute('data-ark-line-id')
                || null;

            let detail = null;

            if (pending.kind === 'visit-reason') {
                detail = {
                    task: 'dragon-service-advisor-visit-reason',
                    context: { autoGenerate: true },
                };
            } else if (pending.kind === 'concern-narrative') {
                detail = {
                    task: 'dragon-service-advisor',
                    context: {
                        concernId: pending.concernId,
                        field: pending.field || 'verified_findings',
                        autoGenerate: true,
                    },
                };
            } else if (pending.kind === 'line-note') {
                if (! resolvedLineId) {
                    this.surfaceWorksheetMessage('Saved. Open Rewrite on the note to polish it.', { tone: 'warn' });

                    return;
                }

                detail = {
                    task: 'dragon-service-advisor-line-note',
                    context: {
                        lineId: Number(resolvedLineId) || resolvedLineId,
                        autoGenerate: true,
                    },
                };
            }

            if (! detail) {
                return;
            }

            requestAnimationFrame(() => {
                window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', { detail }));
            });
        },
    };
};

export function arkWorksheetFormSubmit(eventOrForm) {
    const form = eventOrForm instanceof HTMLFormElement
        ? eventOrForm
        : (eventOrForm?.target?.closest?.('form')
            ?? (eventOrForm?.currentTarget instanceof HTMLFormElement ? eventOrForm.currentTarget : null));

    if (! (form instanceof HTMLFormElement)) {
        return Promise.resolve(false);
    }

    const root = form.closest('[data-worksheet-root]');

    if (! root) {
        HTMLFormElement.prototype.submit.call(form);

        return Promise.resolve(true);
    }

    const worksheet = window.Alpine?.$data?.(root) ?? root._x_dataStack?.[0];

    if (typeof worksheet?.submitWorksheetForm === 'function') {
        return Promise.resolve(worksheet.submitWorksheetForm(form));
    }

    HTMLFormElement.prototype.submit.call(form);

    return Promise.resolve(true);
}

export function initPartProcurementSelect() {
    document.addEventListener('change', (event) => {
        const select = event.target.closest('[data-procurement-select]');

        if (! select) {
            return;
        }

        const form = select.closest('[data-procurement-form]');

        if (! form) {
            return;
        }

        const currentState = select.dataset.currentState ?? '';

        if (select.value === currentState) {
            return;
        }

        arkWorksheetFormSubmit(form);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-procurement-form]');

        if (! form) {
            return;
        }

        event.preventDefault();
        arkWorksheetFormSubmit(form);
    }, true);
}

function hideLifecycleLostPanel(form) {
    const panel = form?.querySelector('[data-lost-reason-panel]');

    if (! panel) {
        return;
    }

    panel.hidden = true;
    panel.classList.add('hidden');
}

function openLifecycleReviewModal(form, select) {
    window.dispatchEvent(new CustomEvent('ark-workspace-modal-open', {
        detail: {
            task: 'review-request',
            context: {
                closePaid: true,
                lifecycleFormId: form?.id || null,
            },
            invokeEl: select instanceof HTMLElement ? select : null,
        },
    }));
}

function resetLifecycleSelect(form) {
    const select = form?.querySelector('[data-lifecycle-select]');

    if (select) {
        select.value = select.dataset.currentStatus ?? select.value;
    }
}

export function initRepairOrderLifecycleSelect() {
    document.addEventListener('change', (event) => {
        const select = event.target.closest('[data-lifecycle-select]');

        if (! select) {
            return;
        }

        const form = select.closest('[data-lifecycle-form]');

        if (! form) {
            return;
        }

        const lostPanel = form.querySelector('[data-lost-reason-panel]');
        const currentStatus = select.dataset.currentStatus ?? '';

        if (select.value === 'closed:lost') {
            if (lostPanel) {
                lostPanel.hidden = false;
                lostPanel.classList.remove('hidden');
            }

            return;
        }

        if (select.value === 'closed:paid') {
            hideLifecycleLostPanel(form);
            openLifecycleReviewModal(form, select);

            return;
        }

        hideLifecycleLostPanel(form);

        const selectedOption = select.options[select.selectedIndex];
        const blockedReason = selectedOption?.dataset?.blockedReason ?? '';

        if (blockedReason !== '') {
            select.value = currentStatus;
            window.alert(blockedReason);

            return;
        }

        if (select.value !== currentStatus) {
            arkWorksheetFormSubmit(form);
        }
    });

    document.addEventListener('click', (event) => {
        const cancel = event.target.closest('[data-lost-reason-cancel]');

        if (! cancel) {
            return;
        }

        const form = cancel.closest('[data-lifecycle-form]');

        hideLifecycleLostPanel(form);
        resetLifecycleSelect(form);
    });

    window.addEventListener('ark-review-request-dismissed', (event) => {
        const formId = event.detail?.lifecycleFormId;
        const form = formId
            ? document.getElementById(formId)
            : document.querySelector('[data-lifecycle-form]');

        resetLifecycleSelect(form);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-lifecycle-form]');

        if (! form) {
            return;
        }

        event.preventDefault();
        arkWorksheetFormSubmit(form);
    }, true);

    // Job Board close choices deep-link here so paid/lost confirmation panels can run.
    applyLifecycleQueryIntent();
}

function applyLifecycleQueryIntent() {
    const params = new URLSearchParams(window.location.search);
    const lifecycle = params.get('lifecycle');

    if (! lifecycle) {
        return;
    }

    const select = document.querySelector('[data-lifecycle-select]');

    if (! select) {
        return;
    }

    const option = Array.from(select.options).find((entry) => entry.value === lifecycle);

    if (! option || option.disabled) {
        return;
    }

    select.value = lifecycle;
    select.dispatchEvent(new Event('change', { bubbles: true }));

    params.delete('lifecycle');
    const query = params.toString();
    const nextUrl = `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`;
    window.history.replaceState({}, '', nextUrl);
}

