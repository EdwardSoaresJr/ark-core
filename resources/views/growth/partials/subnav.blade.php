<nav class="flex flex-wrap gap-2 border-b border-slate-200 pb-2 text-xs">
    <a
        href="{{ route('growth.opportunities.index') }}"
        class="rounded-sm px-2 py-1 font-semibold no-underline {{ request()->routeIs('growth.opportunities.*') ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}"
    >
        Opportunities
    </a>
    <a
        href="{{ route('growth.integrations.index') }}"
        class="rounded-sm px-2 py-1 font-semibold no-underline {{ request()->routeIs('growth.integrations.*') ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}"
    >
        Integrations
    </a>
    <a
        href="{{ route('growth.dashboard') }}"
        class="rounded-sm px-2 py-1 font-semibold no-underline {{ request()->routeIs('growth.dashboard') ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}"
    >
        Dashboard
    </a>
    <a
        href="{{ route('growth.entity-health') }}"
        class="rounded-sm px-2 py-1 font-semibold no-underline {{ request()->routeIs('growth.entity-health*') ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}"
    >
        Entity Health
    </a>
    <a
        href="{{ route('growth.audit') }}"
        class="rounded-sm px-2 py-1 font-semibold no-underline {{ request()->routeIs('growth.audit') ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}"
    >
        SEO Audit
    </a>
    <a
        href="{{ route('growth.revenue-explorer') }}"
        class="rounded-sm px-2 py-1 font-semibold no-underline {{ request()->routeIs('growth.revenue-explorer') ? 'bg-slate-950 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}"
    >
        Revenue Explorer
    </a>
</nav>
