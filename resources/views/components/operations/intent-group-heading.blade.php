@props([
    'presentation',
])

<div class="ops-review-panel-header">
    <p @class(['ops-eyebrow', $presentation->groupHeadingClass()])>
        {{ $presentation->staffLabel() }}
    </p>
</div>
