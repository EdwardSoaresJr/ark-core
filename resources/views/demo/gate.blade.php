<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>ARK Public Demo</title>
        @include('partials.branding._favicons')
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                background: #e8edf3;
                color: #0f172a;
                font-family: ui-sans-serif, system-ui, sans-serif;
                -webkit-font-smoothing: antialiased;
            }
            main {
                max-width: 40rem;
                margin: 0 auto;
                padding: 4.5rem 1.25rem 3rem;
            }
            .mark {
                margin: 0;
                font-size: 0.8125rem;
                font-weight: 700;
                letter-spacing: 0.06em;
            }
            h1 {
                margin: 0.75rem 0 0;
                font-size: 1.75rem;
                font-weight: 650;
                letter-spacing: -0.02em;
                line-height: 1.25;
            }
            .actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
                margin-top: 1.75rem;
            }
            a.action {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 2.5rem;
                padding: 0.45rem 0.95rem;
                border-radius: 0.125rem;
                font-size: 0.9375rem;
                font-weight: 700;
                text-decoration: none;
            }
            a.action:focus-visible,
            a.hosted:focus-visible {
                outline: 2px solid #0f172a;
                outline-offset: 2px;
            }
            .enter {
                background: #fff;
                color: #0f172a;
                box-shadow: inset 0 0 0 1px #94a3b8;
            }
            .source {
                background: #0f172a;
                color: #fff;
            }
            .hosted {
                display: inline-block;
                margin-top: 1.25rem;
                color: #475569;
                font-size: 0.875rem;
            }
        </style>
    </head>
    <body>
        <main>
            <p class="mark">ARK Public Demo</p>
            <h1>Explore the open-source shop management system.</h1>
            <p class="actions">
                <a class="action enter" href="{{ $enterUrl }}">Open the Demo</a>
                <a class="action source" href="{{ $githubUrl }}">View on GitHub</a>
            </p>
            <a class="hosted" href="{{ $hostedUrl }}">Hosted ARK coming soon</a>
        </main>
    </body>
</html>
