<x-website.layout :website="$website" :seo="$seo" page="book">
    <article class="public-static-page">
        <h1 class="public-page-title">Book an appointment</h1>
        <p class="public-page-lede">Tell us what the car is doing. An advisor confirms the time during business hours. Sending this form does not reserve a bay.</p>
        @include('website.lead-form', ['page' => 'book'])
    </article>
</x-website.layout>
