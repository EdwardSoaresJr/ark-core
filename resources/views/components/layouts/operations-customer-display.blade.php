@props(['title' => 'Your estimate', 'refreshSeconds' => null, 'fragmentUrl' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    @include('partials.branding._favicons')
    @vite(['resources/css/app.css'])
</head>
<body class="h-full overflow-hidden bg-slate-100 text-slate-950 antialiased">
    {{ $slot }}

    @if ($refreshSeconds && $fragmentUrl)
        <script>
            (function () {
                const board = document.getElementById('ops-customer-display-board');
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

                        if (! response.ok) {
                            return;
                        }

                        board.innerHTML = await response.text();
                    } catch (error) {
                        // Counter display should fail quietly and retry.
                    }
                }, refreshMs);
            })();
        </script>
    @endif
</body>
</html>
