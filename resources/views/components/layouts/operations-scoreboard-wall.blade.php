@props(['title' => 'Shop scoreboard', 'refreshSeconds' => 60, 'fragmentUrl' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    @include('partials.branding._favicons')
    @vite(['resources/css/app.css'])
    <style>
        .scoreboard-kpis { grid-template-columns: repeat(5, minmax(0, 1fr)); }
        @media (max-width: 1100px) {
            .scoreboard-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 640px) {
            .scoreboard-kpis { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="h-full overflow-hidden bg-slate-100 text-slate-950 antialiased">
    {{ $slot }}

    @if ($refreshSeconds && $fragmentUrl)
        <script>
            (function () {
                const board = document.getElementById('shop-scoreboard-board');
                const fragmentUrl = @json($fragmentUrl);
                const refreshMs = @json((int) $refreshSeconds * 1000);

                if (! board || refreshMs < 4000) {
                    return;
                }

                window.setInterval(async function () {
                    try {
                        const response = await fetch(fragmentUrl, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });

                        if (! response.ok || response.redirected) {
                            return;
                        }

                        board.innerHTML = await response.text();
                    } catch (error) {
                        // The last good snapshot stays on screen.
                    }
                }, refreshMs);
            })();
        </script>
    @endif
</body>
</html>
