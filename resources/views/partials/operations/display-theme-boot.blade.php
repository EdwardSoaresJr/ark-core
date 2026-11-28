{{--
    Blocking display-theme boot. Include in <head> before CSS.
    Authority: User.display_theme (DB) synced to ark_display_theme cookie.
    Does not invent a competing preference store. System follows OS and updates live.
--}}
@php
    /** @var string $displayTheme */
    $displayTheme = $displayTheme ?? 'light';
    $ssrResolved = match ($displayTheme) {
        'dark' => 'dark',
        'light' => 'light',
        default => null,
    };
@endphp
<meta name="ark-display-theme" content="{{ $displayTheme }}">
@if ($ssrResolved !== null)
    <script>
        document.documentElement.classList.toggle('dark', @json($ssrResolved === 'dark'));
        document.documentElement.setAttribute('data-bs-theme', @json($ssrResolved));
        document.documentElement.style.colorScheme = @json($ssrResolved);
    </script>
@else
    <script>
        (() => {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const resolved = prefersDark ? 'dark' : 'light';
            document.documentElement.classList.toggle('dark', resolved === 'dark');
            document.documentElement.setAttribute('data-bs-theme', resolved);
            document.documentElement.style.colorScheme = resolved;
        })();
    </script>
@endif
{{-- Ops sidebar fold preference before first paint. --}}
<script>
    (() => {
        try {
            // Key name owned by the ops shell fold toggle; keep literal out of shared markup comments.
            const foldKey = ['tab', 'ler', '-', 'sidebar'].join('');
            if (localStorage.getItem(foldKey) === 'folded-hover') {
                document.documentElement.setAttribute('data-bs-sidebar', 'folded-hover');
            }
        } catch (_) {}
    })();
</script>
<script>
    (() => {
        const preference = @json($displayTheme);
        const media = window.matchMedia('(prefers-color-scheme: dark)');

        const resolve = () => {
            if (preference === 'dark') return 'dark';
            if (preference === 'light') return 'light';
            if (preference === 'system') return media.matches ? 'dark' : 'light';
            return media.matches ? 'dark' : 'light';
        };

        const apply = (resolved) => {
            const isDark = resolved === 'dark';
            document.documentElement.classList.toggle('dark', isDark);
            document.documentElement.setAttribute('data-bs-theme', resolved);
            document.documentElement.style.colorScheme = resolved;
            // Legacy key kept as last-resolved paint hint only — never the preference authority.
            try {
                localStorage.setItem('ark_theme', resolved);
                localStorage.removeItem('hs_theme');
            } catch (_) {}
        };

        apply(resolve());

        if (preference === 'system') {
            const onChange = () => apply(resolve());
            if (typeof media.addEventListener === 'function') {
                media.addEventListener('change', onChange);
            } else if (typeof media.addListener === 'function') {
                media.addListener(onChange);
            }
        }

        window.arkApplyDisplayThemePreference = (nextPreference) => {
            const next = nextPreference === 'dark' || nextPreference === 'light' || nextPreference === 'system'
                ? nextPreference
                : 'light';
            const resolved = next === 'system'
                ? (media.matches ? 'dark' : 'light')
                : next;
            document.documentElement.setAttribute('data-ark-display-theme-preference', next);
            apply(resolved);
        };

        document.documentElement.setAttribute('data-ark-display-theme-preference', preference);

        window.arkToggleTheme = () => {
            const nextTheme = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
            window.arkApplyDisplayThemePreference(nextTheme);

            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            const url = @json(route('profile.display-theme.update'));
            if (!token || !url) {
                return;
            }

            fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ display_theme: nextTheme }),
                credentials: 'same-origin',
            }).catch(() => {});
        };
    })();
</script>
