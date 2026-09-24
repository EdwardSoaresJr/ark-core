/**
 * Honest unsaved-change detection for worksheet / workspace forms.
 * Compare live values to the browser's default* baseline - not a sticky flag.
 */

const IGNORE_SELECTOR = '[data-ark-workspace-dirty="off"], [type="search"], .ops-workspace-tabs';

export function plainControlChanged(field) {
    if (! field || field.disabled || field.readOnly) {
        return false;
    }

    const tag = String(field.tagName || '').toUpperCase();
    const type = String(field.type || '').toLowerCase();

    if (tag === 'SELECT' || type === 'select-one' || type === 'select-multiple') {
        const options = [...(field.options || [])];
        const defaultOption = options.find((option) => option.defaultSelected);

        if (defaultOption) {
            return field.value !== defaultOption.value;
        }

        const first = options[0];

        return first ? field.value !== first.value : field.value !== '';
    }

    if (type === 'checkbox' || type === 'radio') {
        return field.checked !== field.defaultChecked;
    }

    if (['hidden', 'button', 'submit', 'reset', 'file', 'image'].includes(type)) {
        return false;
    }

    if (type === 'number') {
        const live = field.value === '' ? null : Number(field.value);
        const baseline = field.defaultValue === '' ? null : Number(field.defaultValue);

        if (Number.isNaN(live) || Number.isNaN(baseline)) {
            return field.value !== field.defaultValue;
        }

        return live !== baseline;
    }

    return field.value !== field.defaultValue;
}

export function fieldHasChanges(field) {
    if (!(field instanceof HTMLElement)) {
        return false;
    }

    if (field.closest(IGNORE_SELECTOR)) {
        return false;
    }

    return plainControlChanged(field);
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
