/**
 * Keep the active Job Board status tab visible in the horizontal strip
 * without scrolling the page itself.
 */
function scrollActiveLaneTabIntoView(root = document) {
    root.querySelectorAll('[data-job-board-lane-tabs]').forEach((nav) => {
        const track = nav.querySelector('[data-job-board-lane-tabs-track]') || nav;
        const active = nav.querySelector(
            '[data-job-board-lane-tab].active, [data-job-board-lane-tab][aria-current="page"]',
        );
        if (! active || typeof active.scrollIntoView !== 'function') {
            return;
        }

        const trackRect = track.getBoundingClientRect();
        const activeRect = active.getBoundingClientRect();
        const fullyVisible =
            activeRect.left >= trackRect.left - 1
            && activeRect.right <= trackRect.right + 1;

        if (fullyVisible) {
            return;
        }

        active.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
            inline: 'center',
        });
    });
}

export function initJobBoardLaneTabs() {
    const run = () => scrollActiveLaneTabIntoView(document);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        run();
    }

    window.addEventListener('pageshow', run);
    document.addEventListener('livewire:navigated', run);
}
