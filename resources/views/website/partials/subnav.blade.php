@php
    $activeTab = $activeTab ?? (request()->routeIs('website.page-media*') ? 'page-media' : (request()->routeIs('website.manage*') ? 'manage' : 'performance'));
@endphp

<nav class="flex flex-wrap gap-1 border-b border-slate-200 pb-2">
    <a
        href="{{ route('website.manage') }}"
        @class([
            'rounded-md px-3 py-1.5 text-sm font-semibold no-underline',
            'bg-slate-950 text-white' => $activeTab === 'manage',
            'text-slate-600 hover:bg-slate-50 hover:text-slate-950' => $activeTab !== 'manage',
        ])
    >
        Manage
    </a>
    <a
        href="{{ route('website.page-media.index') }}"
        @class([
            'rounded-md px-3 py-1.5 text-sm font-semibold no-underline',
            'bg-slate-950 text-white' => $activeTab === 'page-media',
            'text-slate-600 hover:bg-slate-50 hover:text-slate-950' => $activeTab !== 'page-media',
        ])
    >
        Page media
    </a>
    <a
        href="{{ route('website.performance') }}"
        @class([
            'rounded-md px-3 py-1.5 text-sm font-semibold no-underline',
            'bg-slate-950 text-white' => $activeTab === 'performance',
            'text-slate-600 hover:bg-slate-50 hover:text-slate-950' => $activeTab !== 'performance',
        ])
    >
        Performance
    </a>
</nav>
