<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ \App\Support\Branding\Branding::tabTitle() }}</title>

        @include('partials.branding._favicons')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        {{-- Login must stay up during deploy; ReleasePanel activates before post-deploy finishes. --}}
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                body { margin: 0; min-height: 100vh; background: #f1f5f9; color: #020617; font-family: ui-sans-serif, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
                label { display: block; font-size: 0.875rem; font-weight: 500; color: #334155; }
                input[type="email"], input[type="password"], input[type="text"] { margin-top: 0.25rem; display: block; width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 0.125rem; padding: 0.5rem 0.75rem; font-size: 0.875rem; }
                input[type="checkbox"] { margin: 0; }
                button, .btn-primary { display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 0.125rem; background: #020617; color: #fff; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 700; cursor: pointer; }
                a { color: #475569; font-size: 0.875rem; }
                .text-red-600, [class*="text-red"] { color: #dc2626; font-size: 0.875rem; }
            </style>
        @endif
    </head>
    <body class="font-sans text-slate-950 antialiased">
        <div class="flex min-h-screen flex-col items-center bg-slate-100 pt-6 sm:justify-center sm:pt-0">
            <div>
                <a href="/">
                    <img src="{{ \App\Support\Branding\Branding::loginImage() }}" alt="{{ config('app.name', 'ARK-SMS') }}" style="max-height: 48px; width: auto;">
                </a>
            </div>

            <div class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-sm sm:max-w-md sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
