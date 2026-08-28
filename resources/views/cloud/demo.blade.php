<x-cloud.shell title="Get your shop online">
    <div class="mx-auto max-w-3xl px-5 sm:px-8 py-20 sm:py-28 cloud-stage text-center">
        <h1 class="cloud-display text-4xl sm:text-5xl font-semibold leading-tight">
            Ready to get your shop online?
        </h1>
        <p class="mt-6 text-xl text-[var(--cloud-muted)] max-w-xl mx-auto leading-relaxed">
            Start running your shop in minutes. Name your shop, invite your team,
            and write your first repair order — the same tools used on a busy Tuesday floor.
        </p>
        <div class="mt-12 flex flex-wrap justify-center gap-4">
            <a href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('trial.shop') }}" class="cloud-btn-primary text-lg !px-9 !py-4">Start Free Trial</a>
            <a href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('home') }}#product" class="cloud-btn-ghost !px-7 !py-3.5">See the product</a>
        </div>
    </div>
</x-cloud.shell>
