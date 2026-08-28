<section>
    <div class="border-b border-slate-200 pb-2">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Profile</p>
        <h2 class="text-base font-black text-slate-950">{{ __('Profile Information') }}</h2>
        <p class="mt-0.5 text-xs text-slate-500">
            {{ __('This name and email are used for staff login and internal attribution.') }}
        </p>
    </div>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-4 max-w-2xl space-y-4">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" class="text-xs font-semibold uppercase tracking-wide text-slate-500" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full border-slate-300 text-sm text-slate-950 focus:border-slate-500 focus:ring-slate-500" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="phone" :value="__('Cell phone')" class="text-xs font-semibold uppercase tracking-wide text-slate-500" />
            <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full border-slate-300 text-sm text-slate-950 focus:border-slate-500 focus:ring-slate-500" :value="old('phone', $user->phone)" autocomplete="tel" placeholder="(719) 555-0100" />
            <p class="mt-1 text-sm text-slate-600">Used when this staff member is selected for inbound call ringing.</p>
            <x-input-error class="mt-2" :messages="$errors->get('phone')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" class="text-xs font-semibold uppercase tracking-wide text-slate-500" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full border-slate-300 text-sm text-slate-950 focus:border-slate-500 focus:ring-slate-500" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="mt-2 text-sm text-slate-700">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="rounded-md text-sm font-medium text-slate-700 underline hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-sm font-medium text-emerald-700">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                {{ __('Save Profile') }}
            </button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-slate-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
