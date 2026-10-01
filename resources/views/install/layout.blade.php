<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Setup') · ARK</title>
    <style>
        :root {
            --bg: #f1f5f9;
            --card: #ffffff;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --accent: #0e7490;
            --accent-ink: #ffffff;
            --pass: #166534;
            --pass-bg: #dcfce7;
            --warn: #a16207;
            --warn-bg: #fef9c3;
            --fail: #b91c1c;
            --fail-bg: #fee2e2;
            --radius: 10px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(180deg, #e2e8f0 0%, var(--bg) 40%, #eef2ff 100%);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
        }
        .wrap { max-width: 720px; margin: 0 auto; padding: 2rem 1.25rem 3rem; }
        .brand { display: flex; align-items: baseline; gap: .5rem; margin-bottom: 1.25rem; }
        .brand strong { font-size: 1.35rem; letter-spacing: -.02em; }
        .brand span { color: var(--muted); font-size: .9rem; }
        .steps { display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: 1.25rem; }
        .steps span {
            font-size: .72rem; text-transform: uppercase; letter-spacing: .04em;
            padding: .25rem .55rem; border-radius: 999px; border: 1px solid var(--line);
            color: var(--muted); background: #fff;
        }
        .steps span.on { border-color: var(--accent); color: var(--accent); font-weight: 600; }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 1.5rem 1.5rem 1.35rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }
        h1 { font-size: 1.45rem; margin: 0 0 .5rem; letter-spacing: -.02em; }
        p.lead { margin: 0 0 1.25rem; color: var(--muted); line-height: 1.5; }
        label { display: block; font-size: .85rem; font-weight: 600; margin: .85rem 0 .3rem; }
        input, select {
            width: 100%; padding: .6rem .7rem; border: 1px solid #cbd5e1; border-radius: 8px;
            font: inherit; color: var(--ink); background: #fff;
        }
        input:focus, select:focus { outline: 2px solid rgba(14, 116, 144, .35); border-color: var(--accent); }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
        @media (max-width: 640px) { .row { grid-template-columns: 1fr; } }
        .actions { display: flex; flex-wrap: wrap; gap: .6rem; margin-top: 1.35rem; align-items: center; }
        .btn {
            appearance: none; border: 0; border-radius: 8px; padding: .65rem 1rem;
            font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex;
        }
        .btn-primary { background: var(--accent); color: var(--accent-ink); }
        .btn-primary:disabled { opacity: .45; cursor: not-allowed; }
        .btn-secondary { background: #fff; color: var(--ink); border: 1px solid #cbd5e1; }
        .errors { background: var(--fail-bg); color: var(--fail); border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; }
        .errors ul { margin: 0; padding-left: 1.1rem; }
        .status { background: #ecfeff; color: #155e75; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; }
        .check { display: flex; gap: .75rem; padding: .65rem 0; border-bottom: 1px solid var(--line); }
        .check:last-child { border-bottom: 0; }
        .badge { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: .2rem .45rem; border-radius: 6px; height: fit-content; }
        .badge-pass { background: var(--pass-bg); color: var(--pass); }
        .badge-warning { background: var(--warn-bg); color: var(--warn); }
        .badge-fail { background: var(--fail-bg); color: var(--fail); }
        .check .meta { flex: 1; }
        .check .meta strong { display: block; font-size: .92rem; }
        .check .meta span { color: var(--muted); font-size: .82rem; }
        .hint { color: var(--muted); font-size: .82rem; margin-top: .25rem; }
        .cards { display: grid; gap: .75rem; }
        .opt {
            border: 1px solid var(--line); border-radius: 8px; padding: 1rem;
        }
        .opt strong { display: block; margin-bottom: .25rem; }
        .opt span { color: var(--muted); font-size: .85rem; }
        .review dt { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin-top: .75rem; }
        .review dd { margin: .15rem 0 0; font-weight: 600; }
        .ok-box { background: var(--pass-bg); color: var(--pass); border-radius: 8px; padding: .75rem 1rem; margin: 1rem 0; font-size: .9rem; }
        .error-box { background: var(--fail-bg); color: var(--fail); border-radius: 8px; padding: .75rem 1rem; margin: 1rem 0; font-size: .9rem; }
        .db-status {
            display: flex; align-items: baseline; justify-content: space-between; gap: 1rem;
            margin: 1rem 0; padding: .85rem 1rem; border-radius: 8px; border: 1px solid var(--line);
        }
        .db-status.is-ok { background: var(--pass-bg); border-color: #bbf7d0; color: var(--pass); }
        .db-status.is-fail { background: var(--fail-bg); border-color: #fecaca; color: var(--fail); }
        .db-status span { font-weight: 700; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <strong>ARK</strong>
        <span>First-run setup</span>
    </div>
    @if(!empty($steps))
        <div class="steps" aria-label="Setup progress">
            @foreach($steps as $s)
                <span @class(['on' => ($step ?? 0) === $s['n']])>{{ $s['n'] }}. {{ $s['label'] }}</span>
            @endforeach
        </div>
    @endif
    <div class="card">
        @if ($errors->any())
            <div class="errors" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif
        @yield('content')
    </div>
</div>
</body>
</html>
