/**
 * Client refine for inspection capture CTAs.
 * Server already chose a surface; this corrects when UA heuristics miss
 * (touch bay tablets reporting as desktop, etc.).
 */
export function preferredInspectionCaptureSurface() {
    try {
        const touch = (navigator.maxTouchPoints || 0) > 0;
        const coarse = window.matchMedia?.('(pointer: coarse)')?.matches === true;
        const shortSide = Math.min(window.screen?.width || 0, window.screen?.height || 0);

        if (touch && (coarse || shortSide > 0 && shortSide <= 1180)) {
            return 'tablet';
        }
    } catch {
        // Private / restricted environments — keep server href.
    }

    return 'desktop_walk';
}

export function applyInspectionCaptureSurfaceCtas(root = document) {
    root.querySelectorAll('[data-inspection-capture-cta]').forEach((anchor) => {
        if (!(anchor instanceof HTMLAnchorElement)) {
            return;
        }

        const desktopUrl = anchor.dataset.desktopWalkUrl;
        const tabletUrl = anchor.dataset.tabletUrl;

        if (! desktopUrl || ! tabletUrl) {
            return;
        }

        const surface = preferredInspectionCaptureSurface();
        const next = surface === 'tablet' ? tabletUrl : desktopUrl;

        if (next && anchor.getAttribute('href') !== next) {
            anchor.setAttribute('href', next);
            anchor.dataset.captureSurface = surface;
        }
    });
}

export function initInspectionCaptureSurfaceCtas() {
    applyInspectionCaptureSurfaceCtas();
}
