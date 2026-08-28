<x-operations.app title="Repair Order Changed">
    <section class="mx-auto max-w-2xl border border-amber-300 bg-amber-50 p-4 text-amber-950">
        <p class="text-xs font-bold uppercase tracking-[0.08em] text-amber-700">Shared Shop State</p>
        <h1 class="mt-1 text-xl font-black tracking-tight">Estimate changed while you were working.</h1>
        <p class="mt-2 text-sm font-semibold leading-6 text-amber-900">
            {{ $message ?? 'Refresh the worksheet and review the latest estimate state before saving.' }}
        </p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ url()->previous() }}" class="inline-flex min-h-10 items-center justify-center rounded-sm bg-amber-950 px-3 text-sm font-bold text-white hover:bg-amber-900">
                Review Latest State
            </a>
            <a href="{{ route('operations.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-sm border border-amber-300 bg-white px-3 text-sm font-bold text-amber-950 hover:border-amber-400">
                Return To Queue
            </a>
        </div>
    </section>
</x-operations.app>
