<script>
(function () {
    var storageKey = 'ark-customer-theme';
    var cookieName = 'ark_display_theme';
    var cookieDomain = @json(config('ark-ecosystem.cookie_domain'));

    function readCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));

        return match ? decodeURIComponent(match[1]) : null;
    }

    function writeCookie(name, value) {
        var parts = [
            name + '=' + encodeURIComponent(value),
            'path=/',
            'max-age=' + String(60 * 60 * 24 * 365),
            'SameSite=Lax',
        ];

        if (cookieDomain) {
            parts.push('domain=' + cookieDomain);
        }

        if (window.location.protocol === 'https:') {
            parts.push('Secure');
        }

        document.cookie = parts.join('; ');
    }

    function normalizeTheme(value) {
        return value === 'light' || value === 'dark' || value === 'system' ? value : null;
    }

    var fromCookie = normalizeTheme(readCookie(cookieName));
    var fromStorage = null;

    try {
        fromStorage = normalizeTheme(localStorage.getItem(storageKey));
    } catch (error) {
        fromStorage = null;
    }

    var stored = fromCookie || fromStorage;
    var systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    var dark = stored === 'dark' || ((stored === null || stored === 'system') && systemDark);

    if (stored === 'light') {
        dark = false;
    }

    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.dataset.customerTheme = stored === 'light' || stored === 'dark' ? stored : 'system';

    // Keep localStorage and shared cookie aligned across website + portal hosts.
    if (stored === 'light' || stored === 'dark') {
        try {
            localStorage.setItem(storageKey, stored);
        } catch (error) {
            // ignore
        }

        if (fromCookie !== stored) {
            writeCookie(cookieName, stored);
        }
    }

    window.__arkCustomerTheme = {
        storageKey: storageKey,
        cookieName: cookieName,
        cookieDomain: cookieDomain,
        readCookie: readCookie,
        writeCookie: writeCookie,
        normalizeTheme: normalizeTheme,
    };
})();
</script>
