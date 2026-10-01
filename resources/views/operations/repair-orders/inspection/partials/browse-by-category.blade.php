<details class="ops-inspection-browse" id="browse-findings">
    <summary class="ops-inspection-browse__summary">Browse by category</summary>

    @if ($canEdit)
        <details class="ops-inspection-browse__add">
            <summary class="ops-inspection-browse__add-summary">Add item to category</summary>
            <form method="post" action="{{ route('operations.repair-orders.inspection.items.store', $repairOrder) }}" class="ops-inspection-browse__add-form">
                @csrf
                <label class="ops-inspection-field">
                    <span class="ops-inspection-field__label">Category</span>
                    <select name="category" class="ops-inspection-field__input" required>
                        @foreach ($categories as $category)
                            <option value="{{ $category->value }}">{{ $category->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ops-inspection-field">
                    <span class="ops-inspection-field__label">Label</span>
                    <input type="text" name="label" class="ops-inspection-field__input" required maxlength="191">
                </label>
                <label class="ops-inspection-field">
                    <span class="ops-inspection-field__label">Scope link (optional)</span>
                    <select name="repair_order_concern_id" class="ops-inspection-field__input">
                        <option value="">None</option>
                        @foreach ($concerns as $concern)
                            <option value="{{ $concern->id }}">{{ $concern->summary }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="ops-inspection-btn ops-inspection-btn--secondary">Add item</button>
            </form>
        </details>
    @endif

    @forelse ($browse_categories as $browseCategory)
        <section class="ops-inspection-browse__category">
            <h3 class="ops-inspection-browse__category-title">{{ $browseCategory['category']->label() }}</h3>
            <div class="ops-inspection-browse__category-list">
                @foreach ($browseCategory['rows'] as $row)
                    @include('operations.repair-orders.inspection.partials.finding-card', [
                        'finding' => $row['finding'],
                        'item' => $row['item'],
                    ])
                @endforeach
            </div>
        </section>
    @empty
        <p class="ops-inspection-browse__empty">No categorized items yet.</p>
    @endforelse
</details>
