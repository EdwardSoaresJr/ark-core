@props([
    'code',
    'title',
    'message',
    'primaryLabel',
    'primaryUrl',
    'secondaryLabel',
    'secondaryUrl',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ \App\Support\Branding\Branding::tabTitle() }}</title>

        @include('partials.branding._favicons')

        {{-- Error pages must never depend on Vite — deploys can briefly activate a release before assets build. --}}
        <style>
            body { margin: 0; min-height: 100vh; background: #f1f5f9; color: #020617; font-family: ui-sans-serif, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
            main { margin: 0 auto; display: flex; min-height: 100vh; max-width: 42rem; flex-direction: column; justify-content: center; padding: 2.5rem 1rem; }
            .panel { border: 1px solid #cbd5e1; background: #fff; padding: 1.25rem; box-shadow: 0 1px 2px rgb(15 23 42 / 0.06); }
            .code { font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b; }
            h1 { margin: 0.25rem 0 0; font-size: 1.5rem; font-weight: 900; letter-spacing: -0.02em; line-height: 1.2; }
            .message { margin: 0.5rem 0 0; font-size: 0.875rem; font-weight: 500; line-height: 1.5; color: #334155; }
            .actions { margin-top: 1.25rem; display: flex; flex-wrap: wrap; gap: 0.5rem; }
            .btn { display: inline-flex; min-height: 2.5rem; align-items: center; justify-content: center; border-radius: 0.125rem; padding: 0 0.75rem; font-size: 0.875rem; font-weight: 700; text-decoration: none; }
            .btn-primary { background: #020617; color: #fff; }
            .btn-primary:hover { background: #1e293b; }
            .btn-secondary { border: 1px solid #cbd5e1; background: #fff; color: #0f172a; }
            .btn-secondary:hover { border-color: #94a3b8; }
        </style>
    </head>
    <body>
        <main>
            <div class="panel">
                <p class="code">Error {{ $code }}</p>
                <h1>{{ $title }}</h1>
                <p class="message">{{ $message }}</p>

                <div class="actions">
                    <a href="{{ $primaryUrl }}" class="btn btn-primary">{{ $primaryLabel }}</a>
                    <a href="{{ $secondaryUrl }}" class="btn btn-secondary">{{ $secondaryLabel }}</a>
                </div>
            </div>
        </main>
    </body>
</html>
