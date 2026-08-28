<x-cloud.shell title="Your account" :footer="false">
    <x-cloud.trial-frame :step="$step" title="Your account">
        <p class="mt-3 text-[var(--cloud-muted)]">
            You’ll be the owner of <span class="font-semibold text-[var(--cloud-ink-soft)]">{{ $shopName }}</span>.
        </p>

        <form method="post" action="{{ \App\Ark\Platform\Cloud\CloudUrls::route('trial.account.store') }}" class="mt-8 space-y-5">
            @csrf
            <div>
                <label for="owner_name" class="block text-sm font-semibold text-[var(--cloud-ink-soft)] mb-2">Name</label>
                <input id="owner_name" name="owner_name" type="text" required
                       value="{{ old('owner_name', $ownerName) }}"
                       class="cloud-input" autocomplete="name">
                @error('owner_name')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="email" class="block text-sm font-semibold text-[var(--cloud-ink-soft)] mb-2">Email</label>
                <input id="email" name="email" type="email" required
                       value="{{ old('email', $email) }}"
                       placeholder="you@shop.com"
                       class="cloud-input" autocomplete="email">
                @error('email')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-semibold text-[var(--cloud-ink-soft)] mb-2">Password</label>
                <input id="password" name="password" type="password" required minlength="8"
                       class="cloud-input" autocomplete="new-password"
                       placeholder="At least 8 characters">
                @error('password')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between gap-3 pt-2">
                <a href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('trial.workspace') }}" class="cloud-btn-ghost !py-3 !px-5">Back</a>
                <button type="submit" class="cloud-btn-primary !px-8 !py-3.5">Continue</button>
            </div>
        </form>
    </x-cloud.trial-frame>
</x-cloud.shell>
