@props(['title' => 'Financial Authority'])

<aside {{ $attributes->merge(['class' => 'space-y-3 border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900']) }}>
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-500">{{ $title }}</p>

    {{ $slot }}
</aside>
