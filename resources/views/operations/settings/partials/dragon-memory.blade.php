<section x-show="active === 'dragon-memory'" x-cloak class="space-y-4">
    <div class="border-b border-slate-200 pb-2">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Hosted Dragon</p>
        <h2 class="text-base font-black text-slate-950">What Dragon has been taught</h2>
        <p class="mt-0.5 text-xs text-slate-500">Durable standards and preferences stored in ARK. Live shop facts are not listed here.</p>
    </div>

    @if ($dragonMemories->isEmpty())
        <p class="text-sm text-slate-600">Dragon has not been taught any durable memories yet.</p>
    @else
        <div class="space-y-3">
            @foreach ($dragonMemories as $memory)
                <article class="border border-slate-200 p-3">
                    <p class="text-sm text-slate-950">{{ $memory->fact_value }}</p>
                    <p class="mt-1 text-[11px] text-slate-500">
                        {{ $memory->scope_type === 'workstation' ? 'Station' : ($memory->scope_type === 'user' ? 'Personal' : 'Company') }}
                        @if ($memory->workstation)
                            · {{ $memory->workstation->displayLocation() }}
                        @endif
                        @if ($memory->user)
                            · {{ $memory->user->name }}
                        @endif
                        · {{ $memory->taught_by ?: 'unknown' }}
                        · {{ $memory->superseded_at ? 'Forgotten / superseded' : 'Active' }}
                    </p>
                    @if ($memory->superseded_at === null)
                        <form method="POST" action="{{ route('operations.settings.shop.dragon-memory.update', $memory) }}" class="mt-2 space-y-2">
                            @csrf
                            @method('PATCH')
                            <textarea name="fact_value" rows="2" class="w-full rounded-md border border-slate-300 px-2 py-1 text-sm">{{ $memory->fact_value }}</textarea>
                            <button type="submit" class="rounded-md bg-slate-950 px-3 py-1.5 text-xs font-semibold text-white">Correct</button>
                        </form>
                        <form method="POST" action="{{ route('operations.settings.shop.dragon-memory.forget', $memory) }}" class="mt-2" onsubmit="return confirm('Forget this memory?')">
                            @csrf
                            <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">Forget</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</section>
