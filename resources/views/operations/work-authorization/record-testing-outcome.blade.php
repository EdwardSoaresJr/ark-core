<x-operations.app title="Record Testing Outcome" ui="tabler">
    <x-tabler.page
        layout="focused"
        title="Record Testing Outcome"
        pretitle="Work Authorization"
        subtitle="Testing ends with an answer, not a repair."
    >
        <form
            method="POST"
            action="{{ route('operations.repair-orders.work-authorization.outcome.store', [$repairOrder, $authorization]) }}"
            class="card"
            data-testing-outcome-form
        >
            @csrf

            <div class="card-header">
                <div>
                    <h3 class="card-title">RO #{{ $repairOrder->repair_order_id }}</h3>
                    @if ($authorization->concern)
                        <div class="card-subtitle">{{ $authorization->concern->summary }}</div>
                    @endif
                </div>
            </div>

            <div class="card-body">
                <div class="mb-3">
                    <label for="outcome" class="form-label required">Outcome</label>
                    <select
                        id="outcome"
                        name="outcome"
                        required
                        @class(['form-select', 'is-invalid' => $errors->has('outcome')])
                    >
                        <option value="">Choose outcome…</option>
                        @foreach ($outcomes as $outcome)
                            <option value="{{ $outcome->value }}" @selected(old('outcome') === $outcome->value)>
                                {{ $outcome->label() }}
                            </option>
                        @endforeach
                    </select>
                    @error('outcome')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="recommendation" class="form-label">Recommendation</label>
                    <textarea
                        id="recommendation"
                        name="recommendation"
                        rows="4"
                        @class(['form-control', 'is-invalid' => $errors->has('recommendation')])
                        placeholder="Required for Repair recommended or Escalate testing."
                    >{{ old('recommendation') }}</textarea>
                    @error('recommendation')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="card-footer">
                <div class="btn-list justify-content-end">
                    <a
                        href="{{ route('operations.repair-orders.show', $repairOrder) }}#work-authorization"
                        class="btn"
                    >
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Save Outcome
                    </button>
                </div>
            </div>
        </form>
    </x-tabler.page>
</x-operations.app>
