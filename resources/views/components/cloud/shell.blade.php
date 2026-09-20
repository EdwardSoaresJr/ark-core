@props([
    'title' => 'ARK Platform',
    'nav' => true,
    'footer' => true,
    'wide' => false,
])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · ARK</title>
    @include('partials.branding._favicons')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Source+Sans+3:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/cloud-funnel.js'])
    @stack('cloud-head')
    <style>
        :root {
            --cloud-ink: #0b1220;
            --cloud-ink-soft: #1c2a3d;
            --cloud-muted: #5b6b7c;
            --cloud-line: rgba(15, 35, 55, 0.12);
            --cloud-cerulean: #0099cc;
            --cloud-cerulean-deep: #007aa6;
            --cloud-mist: #e8f4f8;
            --cloud-paper: #f6f8fa;
            --cloud-glow: rgba(0, 153, 204, 0.18);
            --font-display: 'Bricolage Grotesque', Georgia, serif;
            --font-body: 'Source Sans 3', ui-sans-serif, system-ui, sans-serif;
        }

        .cloud-body {
            font-family: var(--font-body);
            color: var(--cloud-ink);
            background:
                radial-gradient(1200px 600px at 10% -10%, var(--cloud-glow), transparent 55%),
                radial-gradient(900px 500px at 90% 0%, rgba(28, 70, 110, 0.08), transparent 50%),
                linear-gradient(180deg, #f3f7fa 0%, var(--cloud-paper) 40%, #eef2f5 100%);
            min-height: 100vh;
        }

        .cloud-display {
            font-family: var(--font-display);
            letter-spacing: -0.03em;
        }

        .cloud-nav {
            backdrop-filter: blur(12px);
            background: rgba(246, 248, 250, 0.78);
            border-bottom: 1px solid var(--cloud-line);
        }

        .cloud-btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-radius: 0.5rem;
            background: var(--cloud-cerulean);
            color: #fff;
            font-weight: 600;
            padding: 0.8rem 1.35rem;
            transition: background 160ms ease, transform 160ms ease, box-shadow 160ms ease;
            box-shadow: 0 10px 28px -14px rgba(0, 122, 166, 0.85);
        }

        .cloud-btn-primary:hover {
            background: var(--cloud-cerulean-deep);
            transform: translateY(-1px);
        }

        .cloud-btn-ghost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-radius: 0.5rem;
            border: 1px solid var(--cloud-line);
            background: rgba(255, 255, 255, 0.65);
            color: var(--cloud-ink-soft);
            font-weight: 600;
            padding: 0.8rem 1.35rem;
            transition: border-color 160ms ease, background 160ms ease;
        }

        .cloud-btn-ghost:hover {
            border-color: rgba(0, 153, 204, 0.45);
            background: #fff;
        }

        .cloud-btn-ghost-on-dark {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.28);
            color: #fff;
        }

        .cloud-btn-ghost-on-dark:hover {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.4);
            color: #fff;
        }

        .cloud-hero {
            background: #0b1220;
        }

        .cloud-hero-glow {
            background:
                radial-gradient(900px 520px at 88% 35%, rgba(0, 153, 204, 0.38), transparent 58%),
                radial-gradient(700px 420px at 12% 80%, rgba(0, 153, 204, 0.12), transparent 55%),
                linear-gradient(115deg, #0b1220 0%, #102033 48%, #0a3a52 100%);
        }

        .cloud-input {
            width: 100%;
            border-radius: 0.65rem;
            border: 1px solid var(--cloud-line);
            background: #fff;
            padding: 0.95rem 1.1rem;
            font-size: 1.05rem;
            color: var(--cloud-ink);
            outline: none;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }

        .cloud-input:focus {
            border-color: rgba(0, 153, 204, 0.65);
            box-shadow: 0 0 0 4px var(--cloud-glow);
        }

        .cloud-stage {
            animation: cloud-rise 520ms cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes cloud-rise {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes cloud-pulse {
            0%, 100% { opacity: 0.45; }
            50% { opacity: 1; }
        }

        .cloud-pulse {
            animation: cloud-pulse 1.4s ease-in-out infinite;
        }
    </style>
</head>
<body class="cloud-body antialiased">
    @if ($nav)
        <header class="cloud-nav sticky top-0 z-40">
            <div class="{{ $wide ? 'mx-auto max-w-6xl' : 'mx-auto max-w-5xl' }} flex items-center justify-between px-5 py-4 sm:px-8">
                <a href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('home') }}" class="group flex items-center gap-2.5">
                    <img
                        src="{{ asset('assets/ARK_SMS_FINAL_DROP_IN_PACK/android/ark-96x96.png') }}"
                        alt="ARK"
                        class="h-8 w-8 rounded-md"
                        width="32"
                        height="32"
                    >
                    <span class="cloud-display text-lg font-semibold text-[var(--cloud-ink)] group-hover:text-[var(--cloud-cerulean)] transition-colors">
                        ARK
                    </span>
                </a>
                <nav class="flex items-center gap-0.5 sm:gap-1 text-sm font-medium text-[var(--cloud-muted)]">
                    <a href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('home') }}" class="hidden md:inline px-2.5 py-2 rounded-md hover:text-[var(--cloud-ink)] transition-colors">Home</a>
                    <a href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('features') }}" class="hidden md:inline px-2.5 py-2 rounded-md hover:text-[var(--cloud-ink)] transition-colors">Features</a>
                    @if (\App\Ark\Platform\Cloud\CloudPublicPosture::pricingPublic())
                        <a href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('pricing') }}" class="hidden sm:inline px-2.5 py-2 rounded-md hover:text-[var(--cloud-ink)] transition-colors">Pricing</a>
                    @endif
                    <a href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('resources') }}" class="hidden lg:inline px-2.5 py-2 rounded-md hover:text-[var(--cloud-ink)] transition-colors">Resources</a>
                    <a href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('login') }}" class="px-2.5 py-2 rounded-md hover:text-[var(--cloud-ink)] transition-colors">Login</a>
                    <a
                        href="{{ \App\Ark\Platform\Cloud\CloudPublicPosture::primaryCtaUrl() }}"
                        data-cloud-event="cloud_funnel_homepage_cta"
                        class="cloud-btn-primary !py-2.5 !px-5 !text-sm ml-2 shadow-[0_10px_28px_-12px_rgba(0,122,166,0.9)]"
                    >
                        {{ \App\Ark\Platform\Cloud\CloudPublicPosture::primaryCtaLabel() }}
                    </a>
                </nav>
            </div>
        </header>
    @endif

    <main>
        {{ $slot }}
    </main>

    @if ($footer)
        <footer class="mt-24 border-t border-[var(--cloud-line)]">
            <div class="mx-auto max-w-5xl px-5 py-10 sm:px-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-sm text-[var(--cloud-muted)]">
                <p class="cloud-display text-[var(--cloud-ink-soft)] font-semibold">ARK · Built for independent repair shops</p>
                <p>Spend less time fighting software. More time running the shop.</p>
            </div>
        </footer>
    @endif
</body>
</html>
