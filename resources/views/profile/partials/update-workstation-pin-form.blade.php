<section>
    <div class="mb-3">
        <div class="subheader">Station presence</div>
        <h2 class="h3 mb-1">Workstation PIN</h2>
        <p class="text-secondary mb-0">
            Optional 4-digit PIN for legacy station handoff flows - not your ARK login password. Daily ARK use no longer requires a lock screen.
        </p>
    </div>

    @if (! $user->hasOperatorPin())
        <p class="text-secondary mb-0">
            You do not have a station PIN yet. Set one here if you still use PIN-based station handoff; it is optional for normal ARK sessions.
        </p>
    @else
        <form method="post" action="{{ route('profile.workstation-pin.update') }}" data-profile-workstation-pin-form>
            @csrf
            @method('patch')

            <div class="mb-3">
                <label for="update_workstation_pin_password" class="form-label">ARK password</label>
                <input id="update_workstation_pin_password" name="password" type="password" @class(['form-control', 'is-invalid' => $errors->updateWorkstationPin->has('password')]) autocomplete="current-password">
                @foreach ($errors->updateWorkstationPin->get('password') as $message)
                    <div class="invalid-feedback">{{ $message }}</div>
                @endforeach
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label for="update_workstation_pin" class="form-label">New PIN</label>
                    <input id="update_workstation_pin" name="pin" type="tel" maxlength="4" pattern="\d{4}" inputmode="numeric" autocomplete="one-time-code" data-lpignore="true" data-1p-ignore @class(['form-control font-monospace', 'is-invalid' => $errors->updateWorkstationPin->has('pin')])>
                    @foreach ($errors->updateWorkstationPin->get('pin') as $message)
                        <div class="invalid-feedback">{{ $message }}</div>
                    @endforeach
                </div>

                <div class="col-6">
                    <label for="update_workstation_pin_confirmation" class="form-label">Confirm PIN</label>
                    <input id="update_workstation_pin_confirmation" name="pin_confirmation" type="tel" maxlength="4" pattern="\d{4}" inputmode="numeric" autocomplete="one-time-code" data-lpignore="true" data-1p-ignore @class(['form-control font-monospace', 'is-invalid' => $errors->updateWorkstationPin->has('pin_confirmation')])>
                    @foreach ($errors->updateWorkstationPin->get('pin_confirmation') as $message)
                        <div class="invalid-feedback">{{ $message }}</div>
                    @endforeach
                </div>
            </div>

            <div class="d-flex align-items-center gap-3">
                <button type="submit" class="btn btn-primary">
                    Save PIN
                </button>

                @if (session('status') === 'workstation-pin-updated')
                    <span
                        x-data="{ show: true }"
                        x-show="show"
                        x-transition
                        x-init="setTimeout(() => show = false, 2000)"
                        class="text-success"
                    >Saved.</span>
                @endif
            </div>
        </form>
    @endif
</section>

<script>
    document.querySelectorAll('[data-profile-workstation-pin-form] input[name="pin"], [data-profile-workstation-pin-form] input[name="pin_confirmation"]').forEach((field) => {
        field.addEventListener('input', (event) => {
            const input = event.currentTarget;
            input.value = String(input.value).replace(/\D/g, '').slice(0, 4);
        });
    });
</script>
