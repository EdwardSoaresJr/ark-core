@php
    /** @var list<string> $missing */
@endphp

<x-operations.app title="Voice">
    <div class="mx-auto max-w-3xl space-y-4 px-4 py-4">
        <header class="space-y-2 border-b border-slate-200 pb-4">
            <h1 class="text-xl font-black text-slate-950">Voice</h1>
            <p class="text-sm font-semibold text-amber-700">Database setup required</p>
        </header>

        <section class="space-y-3 rounded-sm border border-amber-200 bg-amber-50 p-4">
            <p class="text-sm text-amber-950">
                This deployment is missing voice endpoint schema from the latest release. Voice setup cannot load until migrations run on production.
            </p>

            @if ($missing !== [])
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wide text-amber-800">Missing</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-950">
                        @foreach ($missing as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-sm border border-amber-200 bg-white p-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">On the production app container</p>
                <pre class="mt-2 overflow-x-auto text-xs leading-5 text-slate-800">php artisan migrate --force --no-interaction
php artisan db:seed --class=CommunicationDeviceModelSeeder --force --no-interaction</pre>
            </div>

            <p class="text-xs text-amber-900">
                After migrations complete, reload this page. If errors persist, check <code class="rounded bg-white px-1 py-0.5 text-[11px]">storage/logs/laravel.log</code> on the server.
            </p>
        </section>
    </div>
</x-operations.app>
