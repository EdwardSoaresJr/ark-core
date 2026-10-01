@php
    $headingLevel = $headingLevel ?? 2;
    $headingTag = $headingLevel === 2 ? 'h2' : 'h3';
@endphp

<article class="ops-learn-print__article">
    <header class="ops-learn-print__header">
        <p class="ops-learn-print__role">{{ $article['section']->label }}</p>
        <{{ $headingTag }} class="ops-learn-print__title">{{ $article['title'] }}</{{ $headingTag }}>
        <p class="ops-learn-print__summary">{{ $article['summary'] }}</p>
    </header>

    <div class="ops-learn-prose">
        @include($article['view'])
    </div>
</article>
