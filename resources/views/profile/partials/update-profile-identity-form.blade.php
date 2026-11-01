<section>
    <div class="mb-3">
        <div class="subheader">Profile</div>
        <h2 class="h3 mb-1">{{ __('Profile Information') }}</h2>
        <p class="text-secondary mb-0">
            {{ __('This name and email are used for staff login and internal attribution.') }}
        </p>
    </div>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')

        <div class="mb-3">
            <label for="name" class="form-label">{{ __('Name') }}</label>
            <input id="name" name="name" type="text" @class(['form-control', 'is-invalid' => $errors->has('name')]) value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
            @foreach ($errors->get('name') as $message)
                <div class="invalid-feedback">{{ $message }}</div>
            @endforeach
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">{{ __('Cell phone') }}</label>
            <input id="phone" name="phone" type="text" @class(['form-control', 'is-invalid' => $errors->has('phone')]) value="{{ old('phone', $user->phone) }}" autocomplete="tel" placeholder="(719) 555-0100">
            @foreach ($errors->get('phone') as $message)
                <div class="invalid-feedback">{{ $message }}</div>
            @endforeach
            <div class="form-hint">Used when this staff member is selected for inbound call ringing.</div>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input id="email" name="email" type="email" @class(['form-control', 'is-invalid' => $errors->has('email')]) value="{{ old('email', $user->email) }}" required autocomplete="username">
            @foreach ($errors->get('email') as $message)
                <div class="invalid-feedback">{{ $message }}</div>
            @endforeach

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="alert alert-warning mt-2 mb-0">
                    {{ __('Your email address is unverified.') }}

                    <button form="send-verification" class="btn btn-link p-0 align-baseline">
                        {{ __('Click here to re-send the verification email.') }}
                    </button>

                    @if (session('status') === 'verification-link-sent')
                        <div class="text-success fw-medium mt-2">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('Save Profile') }}
            </button>

            @if (session('status') === 'profile-updated')
                <span
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-success"
                >{{ __('Saved.') }}</span>
            @endif
        </div>
    </form>
</section>
