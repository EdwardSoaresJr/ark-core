@props([
    'intent',
])

<div class="ops-review-panel-header">
    <p @class(['ops-eyebrow', $intent->groupHeadingClass()])>
        {{ $intent->pdfGroupLabel() }}
    </p>
</div>
