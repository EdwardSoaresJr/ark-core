@php
    $groups = $groups ?? \App\Ark\Operations\Leads\Public\CommonProblemSymptomGroups::forDisplay();
    $surfacePage = $surfacePage ?? 'homepage';
@endphp

<div class="public-symptom-groups">
    @foreach ($groups as $group)
        <section class="public-symptom-groups__group" aria-labelledby="symptom-group-{{ Str::slug($group['label']) }}">
            <h3 id="symptom-group-{{ Str::slug($group['label']) }}" class="public-symptom-groups__label">
                {{ $group['label'] }}
            </h3>
            <ul class="public-symptom-groups__list">
                @foreach ($group['problems'] as $problem)
                    <li>
                        <a
                            href="{{ route('public.common-problems.show', $problem['slug']) }}"
                            data-public-surface-common-problem
                            data-public-surface-page="{{ $surfacePage }}"
                            data-public-surface-source="symptom_group"
                            data-public-surface-target="common-problems.{{ $problem['slug'] }}"
                            class="public-symptom-groups__link"
                        >
                            {{ $problem['title'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</div>

<p class="mt-5">
    <a
        href="{{ route('public.common-problems.index') }}"
        data-public-surface-common-problem
        data-public-surface-page="{{ $surfacePage }}"
        data-public-surface-source="view_all"
        data-public-surface-target="common-problems.index"
        class="inline-flex items-center text-sm font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]"
    >
        View all common vehicle problems →
    </a>
</p>
