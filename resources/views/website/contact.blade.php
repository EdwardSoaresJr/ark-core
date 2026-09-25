<x-website.layout :website="$website" :seo="$seo" page="contact">
    <article class="public-static-page">
        <h1 class="public-page-title">Contact {{ $website->shopName() }}</h1>
        <p class="public-page-lede">Call, text, or send a message. For service, request an appointment and we will confirm a time. It does not reserve a bay.</p>
        <ul class="mt-4 text-sm leading-6">
            @if ($website->phone() !== '')
                <li>Phone: {{ $website->phoneDisplay() }}</li>
            @endif
            @if ($website->email() !== '')
                <li>Email: {{ $website->email() }}</li>
            @endif
            @if ($website->address() !== '')
                <li>{{ $website->address() }}</li>
            @endif
        </ul>
        <p class="mt-4"><a class="public-cta public-cta--primary" href="{{ route('public.book') }}">Request an appointment</a></p>

        @if ($website->faqs() !== [])
            <section class="mt-8">
                <h2 class="public-page-title">Questions</h2>
                @foreach ($website->faqs() as $faq)
                    <h3 class="mt-4 font-semibold">{{ $faq['question'] }}</h3>
                    <p class="public-page-lede">{{ $faq['answer'] }}</p>
                @endforeach
            </section>
        @endif

        @include('website.lead-form', ['page' => 'contact'])
    </article>
</x-website.layout>
