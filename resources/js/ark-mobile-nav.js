const DESKTOP_NAV = '(min-width: 768px)';

function focusable(root) {
    return [...root.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])')]
        .filter((element) => element.getClientRects().length > 0);
}

export function initMobileNav() {
    const shell = document.querySelector('[data-ops-mobile-nav]');
    const drawer = shell?.querySelector('[data-ops-mobile-nav-drawer]');
    const main = shell?.querySelector('[data-ops-mobile-nav-main]');
    const backdrop = shell?.querySelector('[data-ops-mobile-nav-backdrop]');
    const openButton = shell?.querySelector('[data-ops-mobile-nav-open]');
    const closeButton = shell?.querySelector('[data-ops-mobile-nav-close]');

    if (! shell || ! drawer || ! main || ! backdrop || ! openButton || ! closeButton) {
        return;
    }

    const desktop = window.matchMedia(DESKTOP_NAV);
    let open = false;

    const setBackgroundInert = (locked) => {
        document.body.classList.toggle('ops-mobile-nav-lock', locked);

        document.querySelectorAll('body > *').forEach((element) => {
            if (element === shell) {
                return;
            }

            if (locked) {
                element.setAttribute('inert', '');
            } else {
                element.removeAttribute('inert');
            }
        });

        if (locked) {
            main.setAttribute('inert', '');
            main.setAttribute('aria-hidden', 'true');
        } else {
            main.removeAttribute('inert');
            main.removeAttribute('aria-hidden');
        }
    };

    const setOpen = (next, { focus = true } = {}) => {
        if (next && desktop.matches) {
            return;
        }

        open = next;
        shell.classList.toggle('is-mobile-nav-open', open);
        openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
        backdrop.hidden = ! open;
        setBackgroundInert(open);

        if (open) {
            drawer.setAttribute('role', 'dialog');
            drawer.setAttribute('aria-modal', 'true');
            drawer.setAttribute('aria-label', 'Navigation');
            closeButton.focus();

            return;
        }

        drawer.removeAttribute('role');
        drawer.removeAttribute('aria-modal');
        drawer.removeAttribute('aria-label');

        if (focus) {
            openButton.focus();
        }
    };

    openButton.addEventListener('click', () => setOpen(true));
    closeButton.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));

    drawer.addEventListener('click', (event) => {
        if (event.target.closest('a')) {
            setOpen(false, { focus: false });
        }
    });

    document.addEventListener('keydown', (event) => {
        if (! open) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);

            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const items = focusable(drawer);

        if (items.length === 0) {
            event.preventDefault();

            return;
        }

        const first = items[0];
        const last = items[items.length - 1];
        const active = document.activeElement;

        if (event.shiftKey && (active === first || ! drawer.contains(active))) {
            event.preventDefault();
            last.focus();
        } else if (! event.shiftKey && (active === last || ! drawer.contains(active))) {
            event.preventDefault();
            first.focus();
        }
    });

    desktop.addEventListener('change', () => {
        if (desktop.matches && open) {
            setOpen(false, { focus: false });
        }
    });
}
