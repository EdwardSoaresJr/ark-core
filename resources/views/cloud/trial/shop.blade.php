<x-cloud.shell title="Get your shop online" :footer="false">
    <x-cloud.trial-frame :step="$step" title="Let’s get your shop online.">
        <p class="mt-4 text-lg text-[var(--cloud-muted)] leading-relaxed">
            This is how customers and your team will recognize you.
        </p>

        <form method="post" action="{{ \App\Ark\Platform\Cloud\CloudUrls::route('trial.shop.store') }}" class="mt-10 space-y-8">
            @csrf
            <div>
                <label for="shop_name" class="block text-sm font-semibold text-[var(--cloud-ink-soft)] mb-2.5">
                    Shop Name
                </label>
                <input
                    id="shop_name"
                    name="shop_name"
                    type="text"
                    value="{{ old('shop_name', $shopName) }}"
                    required
                    autofocus
                    autocomplete="organization"
                    placeholder="Demo Auto Repair"
                    class="cloud-input !py-4 !text-lg"
                >
                @error('shop_name')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="cloud-btn-primary text-base !px-8 !py-3.5">
                    Next →
                </button>
            </div>
        </form>
    </x-cloud.trial-frame>
</x-cloud.shell>
