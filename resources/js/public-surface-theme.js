const STORAGE_KEY = 'ark-customer-theme';
const COOKIE_NAME = 'ark_display_theme';

function themeHelpers() {
    return window.__arkCustomerTheme || null;
}

function readCookieTheme() {
    const helpers = themeHelpers();

    if (! helpers) {
        return null;
    }

    return helpers.normalizeTheme(helpers.readCookie(COOKIE_NAME));
}

function readStoredTheme() {
    const fromCookie = readCookieTheme();

    if (fromCookie === 'light' || fromCookie === 'dark' || fromCookie === 'system') {
        return fromCookie === 'system' ? null : fromCookie;
    }

    try {
        const stored = localStorage.getItem(STORAGE_KEY);

        return stored === 'light' || stored === 'dark' ? stored : null;
    } catch {
        return null;
    }
}

function persistTheme(theme) {
    const helpers = themeHelpers();

    try {
        localStorage.setItem(STORAGE_KEY, theme);
    } catch {
        // Ignore storage failures — still apply for this page view.
    }

    if (helpers?.writeCookie) {
        helpers.writeCookie(COOKIE_NAME, theme);
    }
}

function systemPrefersDark() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function effectiveThemeIsDark() {
    const stored = readStoredTheme();

    if (stored === 'dark') {
        return true;
    }

    if (stored === 'light') {
        return false;
    }

    return systemPrefersDark();
}

function applyPublicSurfaceTheme() {
    const root = document.documentElement;
    const dark = effectiveThemeIsDark();
    const stored = readStoredTheme();

    root.classList.toggle('dark', dark);
    root.dataset.customerTheme = stored ?? 'system';

    document.querySelectorAll('[data-public-surface-theme-toggle]').forEach((toggle) => {
        toggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
        toggle.setAttribute(
            'aria-label',
            dark ? 'Switch to light mode' : 'Switch to dark mode',
        );
        toggle.setAttribute(
            'title',
            stored === null
                ? (dark ? 'Dark mode (device). Click for light.' : 'Light mode (device). Click for dark.')
                : (dark ? 'Switch to light mode' : 'Switch to dark mode'),
        );
    });
}

export function initPublicSurfaceTheme() {
    if (! document.body.classList.contains('public-surface')) {
        return;
    }

    applyPublicSurfaceTheme();

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (readStoredTheme() === null) {
            applyPublicSurfaceTheme();
        }
    });

    document.querySelectorAll('[data-public-surface-theme-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const nextTheme = effectiveThemeIsDark() ? 'light' : 'dark';

            persistTheme(nextTheme);
            applyPublicSurfaceTheme();
        });
    });
}
