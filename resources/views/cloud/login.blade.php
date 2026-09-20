<x-cloud.shell title="Login" :footer="false">
    <div class="mx-auto max-w-sm px-5 sm:px-8 py-12 cloud-stage">
        <h1 class="cloud-display text-2xl font-semibold">Sign in</h1>

        <form method="post" action="{{ \App\Ark\Platform\Cloud\CloudUrls::route('login.store') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-sm font-semibold text-[var(--cloud-ink-soft)] mb-1.5">Email</label>
                <input id="email" name="email" type="email" required value="{{ old('email') }}"
                       class="cloud-input !py-3" autocomplete="username">
                @error('email')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <div class="mb-1.5 flex items-center justify-between gap-3">
                    <label for="password" class="block text-sm font-semibold text-[var(--cloud-ink-soft)]">Password</label>
                    <a href="{{ $forgotPasswordUrl }}" class="text-sm font-medium text-[var(--cloud-cerulean)] hover:underline">
                        Forgot password?
                    </a>
                </div>
                <input id="password" name="password" type="password" required
                       class="cloud-input !py-3" autocomplete="current-password">
                @error('password')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="cloud-btn-primary w-full">Continue</button>
        </form>

        <p class="mt-5 text-center text-sm text-[var(--cloud-muted)]">
            @if (\App\Ark\Platform\Cloud\CloudPublicPosture::signupsOpen())
                New shop?
                <a href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('trial.shop') }}" class="font-semibold text-[var(--cloud-cerulean)] hover:underline">Start Free Trial</a>
            @else
                Looking for hosted ARK?
                <a href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('hosted') }}" class="font-semibold text-[var(--cloud-cerulean)] hover:underline">Ask about hosting</a>
            @endif
        </p>
    </div>
</x-cloud.shell>
