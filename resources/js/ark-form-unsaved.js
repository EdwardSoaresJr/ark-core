/**
 * Honest unsaved-change detection for worksheet / workspace forms.
 * Compare live values to the browser's default* baseline — not a sticky flag.
 */

const IGNORE_SELECTOR = '[data-ark-workspace-dirty="off"], [type="search"], .ops-workspace-tabs';

export function fieldHasChanges(field) {
    if (!(field instanceof HTMLElement)) {
        return false;
    }

    if (field.disabled || field.readOnly) {
        return false;
    }

    if (field.closest(IGNORE_SELECTOR)) {
        return false;
    }

    if (field instanceof HTMLInputElement) {
        if (field.type === 'checkbox' || field.type === 'radio') {
            return field.checked !== field.defaultChecked;
        }

        if (['hidden', 'button', 'submit', 'reset', 'file', 'image'].includes(field.type)) {
            return false;
        }

        if (field.type === 'number') {
            const live = field.value === '' ? null : Number(field.value);
            const baseline = field.defaultValue === '' ? null : Number(field.defaultValue);

            if (Number.isNaN(live) || Number.isNaN(baseline)) {
                return field.value !== field.defaultValue;
            }

            return live !== baseline;
        }

        return field.value !== field.defaultValue;
    }

    if (field instanceof HTMLTextAreaElement) {
        return field.value !== field.defaultValue;
    }

    if (field instanceof HTMLSelectElement) {
        const defaultOption = [...field.options].find((option) => option.defaultSelected);

        if (defaultOption) {
            return field.value !== defaultOption.value;
        }

        // Browser selects the first option when none is marked selected — treat that as baseline.
        const first = field.options[0];

        return first ? field.value !== first.value : field.value !== '';
    }

    return false;
}

export function formHasChanges(form) {
    if (!(form instanceof HTMLFormElement)) {
        return false;
    }

    if (form.closest(IGNORE_SELECTOR) || form.matches('[data-lifecycle-form]')) {
        return false;
    }

    const fields = form.querySelectorAll('input, textarea, select');

    for (const field of fields) {
        if (fieldHasChanges(field)) {
            return true;
        }
    }

    return false;
}

/**
 * @param {ParentNode | null | undefined} root
 */
export function rootHasUnsavedFormChanges(root) {
    if (!root?.querySelectorAll) {
        return false;
    }

    const forms = [...root.querySelectorAll('form')].filter(
        (form) => !form.closest(IGNORE_SELECTOR) && !form.matches('[data-lifecycle-form]'),
    );

    return forms.some(formHasChanges);
}

export function worksheetHasUnsavedChanges() {
    return rootHasUnsavedFormChanges(document.querySelector('[data-worksheet-root]'));
}
