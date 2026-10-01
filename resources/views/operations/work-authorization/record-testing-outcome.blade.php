<x-operations.app title="Record Testing Outcome">
    <div class="mx-auto max-w-xl px-4 py-6">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Work Authorization</p>
        <h1 class="mt-1 text-xl font-semibold text-slate-950">Record Testing Outcome</h1>
        <p class="mt-1 text-sm text-slate-600">
            Testing ends with an answer, not a repair.
            RO #{{ $repairOrder->repair_order_id }}
            @if ($authorization->concern)
                · {{ $authorization->concern->summary }}
            @endif
        </p>

        <form
            method="POST"
            action="{{ route('operations.repair-orders.work-authorization.outcome.store', [$repairOrder, $authorization]) }}"
            class="mt-6 space-y-4 rounded-md border border-slate-200 bg-white p-4"
        >
            @csrf

            <div>
                <label for="outcome" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Outcome</label>
                <select
                    id="outcome"
                    name="outcome"
                    required
                    class="mt-1 w-full rounded-md border-slate-300 text-sm"
                >
                    <option value="">Choose outcome…</option>
                    @foreach ($outcomes as $outcome)
                        <option value="{{ $outcome->value }}" @selected(old('outcome') === $outcome->value)>
                            {{ $outcome->label() }}
                        </option>
                    @endforeach
                </select>
                @error('outcome')
                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="recommendation" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Recommendation</label>
                <textarea
                    id="recommendation"
                    name="recommendation"
                    rows="4"
                    class="mt-1 w-full rounded-md border-slate-300 text-sm"
                    placeholder="Required for Repair recommended or Escalate testing."
                >{{ old('recommendation') }}</textarea>
                @error('recommendation')
                    <p class="mt-1 text-xs text-rose-700">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Save Outcome
                </button>
                <a
                    href="{{ route('operations.repair-orders.show', $repairOrder) }}#work-authorization"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50"
                >
                    Cancel
                </a>
            </div>
        </form>
    </div>
</x-operations.app>
