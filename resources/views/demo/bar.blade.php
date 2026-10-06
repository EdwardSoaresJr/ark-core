@php
    $demoSourcePath = request()->getPathInfo() ?: '/';
@endphp
<style>
    .ark-demo-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem 0.85rem;
        padding: 0.4rem 0.85rem;
        background: #0f172a;
        color: #e2e8f0;
        font-family: ui-sans-serif, system-ui, sans-serif;
        font-size: 0.8125rem;
        line-height: 1.3;
    }
    .ark-demo-bar__kicker {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
    }
    .ark-demo-bar__links {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.45rem;
        margin-left: auto;
    }
    .ark-demo-bar a {
        color: #fff;
        text-underline-offset: 2px;
    }
    .ark-demo-bar a:focus-visible {
        outline: 2px solid #fff;
        outline-offset: 2px;
    }
    .ark-demo-bar__source {
        font-weight: 700;
        text-decoration: underline;
    }
    .ark-demo-bar__hosted {
        color: #cbd5e1;
        font-weight: 500;
        text-decoration: underline;
    }
    @media (max-width: 640px) {
        .ark-demo-bar__links { margin-left: 0; }
    }
    @media print {
        .ark-demo-bar { display: none; }
    }
</style>
<div class="ark-demo-bar" role="note">
    <span class="ark-demo-bar__kicker">ARK Public Demo</span>
    <span>Explore the open-source shop management system.</span>
    <span class="ark-demo-bar__links">
        <a class="ark-demo-bar__source" href="{{ route('demo.github', ['source_path' => $demoSourcePath]) }}">View on GitHub</a>
        <span aria-hidden="true">·</span>
        <a class="ark-demo-bar__hosted" href="{{ route('demo.github', ['source_path' => $demoSourcePath, 'to' => 'hosted']) }}">Hosted ARK coming soon</a>
    </span>
</div>
