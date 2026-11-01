// Each selector accepts the legacy class and the data hook used by the Tabler decision rows.
const QUICK_ADD_DETAILS = '.ops-work-item-quick-add, .ops-decision-schedule-form, [data-decision-quick-add]';
const QUICK_ADD_PANEL = '.ops-work-item-quick-add-panel, .ops-decision-schedule-panel, [data-decision-quick-add-owner]';
const QUICK_ADD_FORM = '.ops-work-item-quick-add-form, [data-work-item-quick-add-form]';
const QUICK_ADD_FEEDBACK = '.ops-work-item-quick-add-feedback, [data-quick-add-feedback]';
const QUICK_ADD_ERROR = '.ops-work-item-quick-add-error, [data-quick-add-error]';
const DECISION_ROW_FOOT = '.ops-decision-pressure-row-foot, [data-decision-row-foot]';
const DECISION_ROW_ACTIONS = '.ops-decision-pressure-row-actions, [data-decision-row-actions]';
const DECISION_ROW_PANEL_SLOT = '.ops-decision-pressure-row-panel-slot, [data-decision-row-panel-slot]';
const SUMMARY_SAVED_CLASSES = ['ops-work-item-quick-add__summary--saved', 'text-success'];

function setQuickAddMessageVisible(element, visible) {
    element.classList.toggle('hidden', ! visible);
    element.hidden = ! visible;
}

function resetQuickAddSummary(summary) {
    const label = summary.dataset.workItemQuickAddLabel;

    if (label) {
        summary.textContent = label;
    }

    summary.classList.remove(...SUMMARY_SAVED_CLASSES);
}

function showQuickAddFeedback(form, message, isError = false) {
    const feedback = form.querySelector(QUICK_ADD_FEEDBACK);
    const error = form.querySelector(QUICK_ADD_ERROR);

    if (feedback) {
        feedback.textContent = isError ? '' : message;
        setQuickAddMessageVisible(feedback, ! isError && message !== '');
    }

    if (error) {
        error.textContent = isError ? message : '';
        setQuickAddMessageVisible(error, isError);
    }
}

function decisionRowDetailsForNode(node) {
    const panel = node?.closest(QUICK_ADD_PANEL);
    const ownerId = panel?.dataset.decisionQuickAddOwner;

    if (ownerId) {
        return document.getElementById(ownerId);
    }

    return node?.closest('details') ?? null;
}

function isDecisionRowExclusiveDetails(details) {
    return details?.matches(QUICK_ADD_DETAILS) ?? false;
}

function decisionRowFoot(details) {
    return details?.closest(DECISION_ROW_FOOT) ?? null;
}

function decisionRowPanelSlot(details) {
    return decisionRowFoot(details)?.querySelector(DECISION_ROW_PANEL_SLOT) ?? null;
}

function decisionRowPanelInDetails(details) {
    return details?.querySelector(QUICK_ADD_PANEL.split(', ').map((selector) => `:scope > ${selector}`).join(', ')) ?? null;
}

function decisionRowPanelForDetails(details) {
    const ownerId = details?.id;

    if (! ownerId) {
        return decisionRowPanelInDetails(details);
    }

    const slottedPanel = decisionRowPanelSlot(details)?.querySelector(
        `[data-decision-quick-add-owner="${ownerId}"]`,
    );

    if (slottedPanel) {
        return slottedPanel;
    }

    return decisionRowPanelInDetails(details);
}

function decisionRowForm(details) {
    return decisionRowPanelForDetails(details)?.querySelector('form') ?? null;
}

function restoreSlottedDecisionRowPanels(foot) {
    foot?.querySelectorAll(DECISION_ROW_PANEL_SLOT.split(', ').map((selector) => `${selector} [data-decision-quick-add-owner]`).join(', ')).forEach((panel) => {
        const details = document.getElementById(panel.dataset.decisionQuickAddOwner ?? '');

        if (details && panel.parentElement !== details) {
            details.appendChild(panel);
        }
    });
}

function mountDecisionRowPanel(details) {
    const foot = decisionRowFoot(details);
    const slot = decisionRowPanelSlot(details);
    const panel = decisionRowPanelForDetails(details);

    if (! foot || ! slot || ! panel || panel.parentElement === slot) {
        return;
    }

    restoreSlottedDecisionRowPanels(foot);
    slot.appendChild(panel);
}

function unmountDecisionRowPanel(details) {
    const panel = decisionRowPanelForDetails(details);

    if (! panel || ! details || panel.parentElement === details) {
        return;
    }

    details.appendChild(panel);
}

function closeDecisionRowExclusiveDetails(exceptDetails = null) {
    const actions = exceptDetails?.closest(DECISION_ROW_ACTIONS);

    if (! actions) {
        return;
    }

    actions.querySelectorAll(QUICK_ADD_DETAILS).forEach((details) => {
        if (details === exceptDetails || ! details.open) {
            return;
        }

        resetQuickAddForm(decisionRowForm(details));
        unmountDecisionRowPanel(details);
        details.open = false;
    });
}

function handleDecisionRowExclusiveDetails(event) {
    const details = event.target;

    if (! isDecisionRowExclusiveDetails(details)) {
        return;
    }

    if (details.open) {
        closeDecisionRowExclusiveDetails(details);
        mountDecisionRowPanel(details);

        return;
    }

    unmountDecisionRowPanel(details);
}

async function handleWorkItemQuickAddSubmit(event) {
    const form = event.target;

    if (! form.matches(QUICK_ADD_FORM)) {
        return;
    }

    event.preventDefault();

    const details = decisionRowDetailsForNode(form);
    const submitButton = form.querySelector('[type="submit"]');
    const summary = details?.querySelector('summary');
    const originalSubmitLabel = submitButton?.textContent ?? 'Save';

    showQuickAddFeedback(form, '', false);

    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Saving…';
    }

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: new FormData(form),
        });

        const data = await response.json().catch(() => ({}));

        if (! response.ok) {
            const message = data.message
                ?? Object.values(data.errors ?? {}).flat()?.[0]
                ?? 'Could not save. Check the form and try again.';

            showQuickAddFeedback(form, message, true);

            if (submitButton) {
                submitButton.textContent = originalSubmitLabel;
            }

            return;
        }

        const notesField = form.querySelector('[name="notes"]');
        const dueField = form.querySelector('[name="due_at"]');

        if (notesField && form.dataset.defaultNotes) {
            notesField.value = form.dataset.defaultNotes;
        }

        if (dueField && form.dataset.defaultDue) {
            dueField.value = form.dataset.defaultDue;
        }

        if (details) {
            unmountDecisionRowPanel(details);
            details.open = false;
        }

        if (summary) {
            summary.classList.add(...SUMMARY_SAVED_CLASSES);
            summary.textContent = data.summary_label ?? 'Added';
            window.setTimeout(() => resetQuickAddSummary(summary), 2200);
        }

        showQuickAddFeedback(form, data.message ?? 'Saved to Work.', false);
    } catch {
        showQuickAddFeedback(form, 'Could not save. Try again.', true);

        if (submitButton) {
            submitButton.textContent = originalSubmitLabel;
        }
    } finally {
        if (submitButton) {
            submitButton.disabled = false;

            if (submitButton.textContent === 'Saving…') {
                submitButton.textContent = originalSubmitLabel;
            }
        }
    }
}

function resetQuickAddForm(form) {
    if (! form) {
        return;
    }

    showQuickAddFeedback(form, '', false);

    const notesField = form.querySelector('[name="notes"]');

    if (notesField) {
        notesField.value = form.dataset.defaultNotes ?? '';
    }

    const dueField = form.querySelector('[name="due_at"]');

    if (dueField && form.dataset.defaultDue) {
        dueField.value = form.dataset.defaultDue;
    }

    const roField = form.querySelector('[name="repair_order_shop_number"]');

    if (roField && roField.type !== 'hidden') {
        roField.value = '';
    }

    const scheduledForField = form.querySelector('[name="scheduled_for"]');

    if (scheduledForField) {
        scheduledForField.value = '';
    }

    const scheduleCustomerField = form.querySelector('[name="schedule_customer"][type="checkbox"]');

    if (scheduleCustomerField) {
        scheduleCustomerField.checked = false;
    }
}

function handleWorkItemQuickAddCancel(event) {
    const button = event.target.closest('[data-work-item-quick-add-cancel]');

    if (! button) {
        return;
    }

    event.preventDefault();

    const details = decisionRowDetailsForNode(button);

    if (! details) {
        return;
    }

    resetQuickAddForm(decisionRowForm(details));
    unmountDecisionRowPanel(details);
    details.open = false;
}

export function initWorkItemQuickAdd() {
    document.addEventListener('submit', handleWorkItemQuickAddSubmit);
    document.addEventListener('click', handleWorkItemQuickAddCancel);
    document.addEventListener('toggle', handleDecisionRowExclusiveDetails, true);
}
