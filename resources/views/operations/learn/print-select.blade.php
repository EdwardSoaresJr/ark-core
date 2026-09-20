<x-operations.app :title="'Print '.\App\Support\Branding\Branding::learnName()">
    <section
        class="ops-learn ops-learn-print-select"
        x-data="arkLearnPrintSelect()"
        data-print-url="{{ route('operations.learn.print') }}"
    >
        <header class="ops-learn__header">
            <div>
                <p class="ops-learn__eyebrow">Staff training</p>
                <h1 class="ops-learn__title">Print {{ \App\Support\Branding\Branding::learnName() }}</h1>
                <p class="ops-learn__lede">Select the guides you want on paper. Whole sections or individual topics — your choice.</p>
            </div>
            <div class="ops-learn-print-select__actions">
                <a href="{{ route('operations.learn.index') }}" class="ops-learn-print-select__btn">Back to guides</a>
                <button
                    type="button"
                    class="ops-learn-print-select__btn ops-learn-print-select__btn--primary"
                    :disabled="selectedCount === 0"
                    @click="openPrintPreview()"
                >
                    Preview &amp; print
                    <span x-show="selectedCount > 0" x-cloak>(<span x-text="selectedCount"></span>)</span>
                </button>
            </div>
        </header>

        <div class="ops-learn-print-select__layout">
            @forelse ($visibleSections as $visibleSection)
                @php
                    $roleArticles = $articlesByRole[$visibleSection->key] ?? [];
                @endphp
                @if ($roleArticles !== [])
                    <section class="ops-learn-print-select__section" data-section="{{ $visibleSection->key }}">
                        <div class="ops-learn-print-select__section-head">
                            <label class="ops-learn-print-select__section-label">
                                <input
                                    type="checkbox"
                                    class="ops-learn-print-select__checkbox"
                                    data-section-toggle="{{ $visibleSection->key }}"
                                    @change="toggleSection('{{ $visibleSection->key }}', $event.target.checked)"
                                >
                                <span class="ops-role-chip {{ $visibleSection->chipClass }}">{{ $visibleSection->label }}</span>
                                <span class="ops-learn-print-select__section-note">Select entire section</span>
                            </label>
                        </div>

                        <ul class="ops-learn-print-select__list">
                            @foreach ($roleArticles as $navArticle)
                                @php
                                    $pick = $visibleSection->key.':'.$navArticle['slug'];
                                @endphp
                                <li>
                                    <label class="ops-learn-print-select__item">
                                        <input
                                            type="checkbox"
                                            class="ops-learn-print-select__checkbox"
                                            name="pick[]"
                                            value="{{ $pick }}"
                                            data-section="{{ $visibleSection->key }}"
                                            x-model="selected"
                                            @change="syncSectionToggle('{{ $visibleSection->key }}')"
                                        >
                                        <span class="ops-learn-print-select__item-copy">
                                            <span class="ops-learn-print-select__item-title">{{ $navArticle['title'] }}</span>
                                            <span class="ops-learn-print-select__item-summary">{{ $navArticle['summary'] }}</span>
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @empty
                <p class="ops-learn__empty">No training guides are assigned to your account yet.</p>
            @endforelse
        </div>
    </section>
</x-operations.app>
