/**
 * Repair-order save policy.
 * Focus, visibility, and background refresh are not edits.
 * A save indicator is only for a user save that is actually in flight.
 */

export function focusReturnPlan({ wasHidden = false, hiddenNow = false, socketPhase = 'live' } = {}) {
    const becameVisible = Boolean(wasHidden) && hiddenNow === false;

    if (! becameVisible) {
        return {
            refresh: false,
            save: false,
            showSaving: false,
        };
    }

    return {
        // Heartbeat still checks estimate version. Reload HTML only when the
        // socket dropped while the worksheet was hidden and events were missed.
        refresh: socketPhase === 'recovering',
        save: false,
        showSaving: false,
    };
}

export function refreshPresentsAsSaving(options = {}) {
    return options?.showSaving === true;
}

export function editShouldAutosave({ changed = false, reason = '' } = {}) {
    if (! changed) {
        return false;
    }

    if (reason === 'focus' || reason === 'visibility' || reason === 'poll' || reason === 'hydrate') {
        return false;
    }

    return reason === 'input' || reason === 'change' || reason === 'blur' || reason === 'commit';
}

export function dirtyAfterSaveAttempt({ ok = false, formDiffers = false, hadUnsavedChanges = false } = {}) {
    if (! ok) {
        return Boolean(hadUnsavedChanges || formDiffers);
    }

    return Boolean(formDiffers);
}
