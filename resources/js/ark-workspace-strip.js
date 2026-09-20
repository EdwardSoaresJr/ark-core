const MOBILE_STRIP_MEDIA = window.matchMedia('(max-width: 991px)');

function mobileStripHidden() {
    return MOBILE_STRIP_MEDIA.matches;
}

function syncModeControlSlots(stripVisible) {
    const toolbarSlot = document.querySelector('[data-ro-mode-toolbar-slot]');
    const stripSlot = document.querySelector('[data-ro-mode-strip-slot]');

    if (! toolbarSlot || ! stripSlot) {
        return;
    }

    const useStripMode = stripVisible && ! mobileStripHidden();
    const toolbarControl = toolbarSlot.querySelector('.ops-ro-mode-control');
    const stripControl = stripSlot.querySelector('.ops-ro-mode-control');

    toolbarSlot.classList.toggle('is-suppressed', useStripMode);

    if (! toolbarControl?.hasAttribute('x-data') && ! stripControl?.hasAttribute('x-data')) {
        return;
    }

    if (useStripMode) {
        toolbarControl?.removeAttribute('data-ro-mode-control');
        stripControl?.setAttribute('data-ro-mode-control', '');
    } else {
        stripControl?.removeAttribute('data-ro-mode-control');
        toolbarControl?.setAttribute('data-ro-mode-control', '');
    }
}

function applyWorkspaceStripVisibility(strip, visible) {
    strip.classList.toggle('is-visible', visible);
    strip.setAttribute('aria-hidden', visible ? 'false' : 'true');
    syncModeControlSlots(visible);
}

function initOrientationHeader() {
    const orientationHeader = document.querySelector('[data-ro-orientation-header]');
    const toolbarSlot = document.querySelector('[data-ro-mode-toolbar-slot]');

    if (! orientationHeader) {
        return false;
    }

    toolbarSlot?.classList.add('is-suppressed');

    const headerControl = orientationHeader.querySelector('[data-ro-mode-control]');
    const toolbarControl = toolbarSlot?.querySelector('.ops-ro-mode-control');

    toolbarControl?.removeAttribute('data-ro-mode-control');
    headerControl?.setAttribute('data-ro-mode-control', '');

    return true;
}

export function initWorkspaceStrip() {
    if (initOrientationHeader()) {
        return;
    }

    const strip = document.querySelector('[data-workspace-strip]');
    const identityBand = document.getElementById('ro-identity-band');

    if (! strip || ! identityBand) {
        return;
    }

    applyWorkspaceStripVisibility(strip, false);

    const observer = new IntersectionObserver((entries) => {
        const entry = entries[0];

        if (! entry) {
            return;
        }

        applyWorkspaceStripVisibility(strip, ! entry.isIntersecting);
    }, {
        threshold: 0,
        rootMargin: '0px',
    });

    observer.observe(identityBand);

    const onMobileLayoutChange = () => {
        const stripVisible = strip.classList.contains('is-visible');
        syncModeControlSlots(stripVisible);
    };

    if (typeof MOBILE_STRIP_MEDIA.addEventListener === 'function') {
        MOBILE_STRIP_MEDIA.addEventListener('change', onMobileLayoutChange);
    } else {
        MOBILE_STRIP_MEDIA.addListener(onMobileLayoutChange);
    }
}
