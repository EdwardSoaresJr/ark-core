<x-website.layout :website="$website" :seo="$seo" page="contact">
    <div class="public-canvas">
        <div class="public-canvas__prose">
            <h1 class="public-page-title">Contact {{ $website->shopName() }}</h1>
            <p class="public-page-lede">Call, text, or send a message. For service, request an appointment and we will confirm a time. It does not reserve a bay.</p>
        </div>
        <div class="public-contact-page__columns">
            <div>
                <ul class="public-canvas__facts">
                    @if ($website->phone() !== '')
                        <li>Phone: {{ $website->phoneDisplay() }}</li>
                    @endif
                    @if ($website->email() !== '')
                        <li>Email: {{ $website->email() }}</li>
                    @endif
                    @if ($website->address() !== '')
                        <li>{{ $website->address() }}</li>
                    @endif
                    @if ($website->hoursLabel() !== '')
                        <li>Hours: {{ $website->hoursLabel() }}</li>
                    @endif
                </ul>
                <p class="mt-4"><a class="public-cta public-cta--primary" href="{{ route('public.book') }}">Request an appointment</a></p>
            </div>
            @if ($website->mapEmbedUrl() !== null)
                <div class="public-contact-page__map">
                    <iframe
                        title="Map to {{ $website->shopName() }}"
                        src="{{ $website->mapEmbedUrl() }}"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"
                    ></iframe>
                </div>
            @endif
        </div>

        <div class="public-contact-page__lower">
            @if ($website->faqs() !== [])
                <section>
                    <h2 class="public-page-title">Questions</h2>
                    @foreach ($website->faqs() as $faq)
                        <h3 class="mt-4 font-semibold">{{ $faq['question'] }}</h3>
                        <p class="public-page-lede">
                            @php
                                $faqAnswer = $faq['answer'];
                                $faqPhrase = 'financing options';
                                $faqLinkAt = strrpos($faqAnswer, $faqPhrase);
                            @endphp
                            @if ($faq['question'] === 'What forms of payment do you accept?' && $faqLinkAt !== false)
                                {{ substr($faqAnswer, 0, $faqLinkAt) }}<a href="{{ route('public.financing') }}">{{ $faqPhrase }}</a>{{ substr($faqAnswer, $faqLinkAt + strlen($faqPhrase)) }}
                            @else
                                {{ $faqAnswer }}
                            @endif
                        </p>
                    @endforeach
                </section>
            @endif

            @include('website.lead-form', ['page' => 'contact'])
        </div>
    </div>
</x-website.layout>
