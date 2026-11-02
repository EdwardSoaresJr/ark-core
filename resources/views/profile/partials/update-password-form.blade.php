<section>
    <div class="mb-3">
        <div class="subheader">Security</div>
        <h2 class="h3 mb-1">{{ __('Update Password') }}</h2>
        <p class="text-secondary mb-0">
            {{ __('Use a long password that is not shared with any other shop system.') }}
        </p>
    </div>

    <form method="post" action="{{ route('password.update') }}">
        @csrf
        @method('put')

        <div class="mb-3">
            <label for="update_password_current_password" class="form-label">{{ __('Current Password') }}</label>
            <input id="update_password_current_password" name="current_password" type="password" @class(['form-control', 'is-invalid' => $errors->updatePassword->has('current_password')]) autocomplete="current-password">
            @foreach ($errors->updatePassword->get('current_password') as $message)
                <div class="invalid-feedback">{{ $message }}</div>
            @endforeach
        </div>

        <div class="mb-3">
            <label for="update_password_password" class="form-label">{{ __('New Password') }}</label>
            <input id="update_password_password" name="password" type="password" @class(['form-control', 'is-invalid' => $errors->updatePassword->has('password')]) autocomplete="new-password">
            @foreach ($errors->updatePassword->get('password') as $message)
                <div class="invalid-feedback">{{ $message }}</div>
            @endforeach
        </div>

        <div class="mb-3">
            <label for="update_password_password_confirmation" class="form-label">{{ __('Confirm Password') }}</label>
            <input id="update_password_password_confirmation" name="password_confirmation" type="password" @class(['form-control', 'is-invalid' => $errors->updatePassword->has('password_confirmation')]) autocomplete="new-password">
            @foreach ($errors->updatePassword->get('password_confirmation') as $message)
                <div class="invalid-feedback">{{ $message }}</div>
            @endforeach
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('Save Password') }}
            </button>

            @if (session('status') === 'password-updated')
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
