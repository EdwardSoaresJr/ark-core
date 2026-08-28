@extends('layouts.learn-print')

@section('content')
    <div class="ops-learn-print">
        <div class="ops-learn-print__toolbar">
            <p class="ops-learn-print__toolbar-title">{{ \App\Support\Branding\Branding::learnName() }} · {{ $articles->count() }} {{ Str::plural('guide', $articles->count()) }}</p>
            <div class="ops-learn-print__toolbar-actions">
                <button type="button" class="ops-learn-print__btn ops-learn-print__btn--primary" onclick="window.print()">Print</button>
                <button type="button" class="ops-learn-print__btn" onclick="window.close()">Close</button>
            </div>
        </div>

        <header class="ops-learn-print__cover">
            <p class="ops-learn-print__cover-eyebrow">Staff training</p>
            <h1 class="ops-learn-print__cover-title">{{ \App\Support\Branding\Branding::learnName() }}</h1>
            <p class="ops-learn-print__cover-meta">{{ \App\Support\Branding\Branding::tabTitle() }} · printed {{ \App\Ark\Operations\Settings\ShopDisplayTimezone::now()->format('M j, Y') }}</p>
        </header>

        @foreach ($articles as $article)
            @include('operations.learn.partials.article-block', ['article' => $article])
        @endforeach
    </div>
@endsection
