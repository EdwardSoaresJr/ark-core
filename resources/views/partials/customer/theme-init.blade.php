<script>
(function () {
    var storageKey = 'ark-customer-theme';
    var cookieName = 'ark_display_theme';
    var cookieDomain = @json(config('ark-ecosystem.cookie_domain'));

    function readCookie(name) {
        var pattern = new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)', 'g');
        var fallback = null;
        var match;

        // A host cookie and a parent-domain cookie can both be present.
        // An explicit day/night choice wins over a leftover "system" value.
        while ((match = pattern.exec(document.cookie)) !== null) {
            var value = decodeURIComponent(match[1]);

            if (value === 'light' || value === 'dark') {
                return value;
            }

            fallback = value;
        }

        return fallback;
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

    var explicitCookie = fromCookie === 'light' || fromCookie === 'dark' ? fromCookie : null;
    var explicitStorage = fromStorage === 'light' || fromStorage === 'dark' ? fromStorage : null;
    var stored = explicitCookie || explicitStorage;
    var systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    var dark = stored === 'dark' || (stored === null && systemDark);

    if (stored === 'light') {
        dark = false;
    }

    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.dataset.customerTheme = stored === 'light' || stored === 'dark' ? stored : 'system';
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';

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
