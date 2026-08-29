<x-cloud.shell title="We’ll host ARK for you">
    <div class="mx-auto max-w-3xl px-5 sm:px-8 py-20 sm:py-28 cloud-stage text-center">
        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-[var(--cloud-cerulean)]">Hosted ARK</p>
        <h1 class="cloud-display mt-4 text-4xl sm:text-5xl font-semibold leading-tight">
            We’ll run the server. You run the shop.
        </h1>
        <p class="mt-6 text-xl text-[var(--cloud-muted)] max-w-xl mx-auto leading-relaxed">
            Self-serve signup and public pricing aren’t open yet. If you don’t want to set up your own server,
            tell us — we can host ARK for your shop when you’re ready.
        </p>
        <div class="mt-12 flex flex-wrap justify-center gap-4">
            <a href="{{ $interestMailto }}" class="cloud-btn-primary text-lg !px-9 !py-4">
                Email us about hosting
            </a>
            <a href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('home') }}#product" class="cloud-btn-ghost !px-7 !py-3.5">
                See the product
            </a>
        </div>
        <p class="mt-8 text-sm text-[var(--cloud-muted)]">
            {{ $interestEmail }}
        </p>
    </div>
</x-cloud.shell>
