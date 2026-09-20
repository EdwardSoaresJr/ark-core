<section>
    <div class="border-b border-slate-200 pb-2">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Station presence</p>
        <h2 class="text-base font-black text-slate-950">Workstation PIN</h2>
        <p class="mt-0.5 text-xs text-slate-500">
            Optional 4-digit PIN for legacy station handoff flows — not your ARK login password. Daily ARK use no longer requires a lock screen.
        </p>
    </div>

    @if (! $user->hasOperatorPin())
        <p class="mt-4 text-sm text-slate-600">
            You do not have a station PIN yet. Set one here if you still use PIN-based station handoff; it is optional for normal ARK sessions.
        </p>
    @else
        <form method="post" action="{{ route('profile.workstation-pin.update') }}" class="ops-profile-workstation-pin-form mt-4 max-w-2xl space-y-4">
            @csrf
            @method('patch')

            <div>
                <x-input-label for="update_workstation_pin_password" value="ARK password" class="text-xs font-semibold uppercase tracking-wide text-slate-500" />
                <x-text-input id="update_workstation_pin_password" name="password" type="password" class="mt-1 block w-full border-slate-300 text-sm text-slate-950 focus:border-slate-500 focus:ring-slate-500" autocomplete="current-password" />
                <x-input-error :messages="$errors->updateWorkstationPin->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_workstation_pin" value="New PIN" class="text-xs font-semibold uppercase tracking-wide text-slate-500" />
                <x-text-input id="update_workstation_pin" name="pin" type="tel" maxlength="4" pattern="\d{4}" inputmode="numeric" autocomplete="one-time-code" data-lpignore="true" data-1p-ignore class="mt-1 block w-full border-slate-300 font-mono text-sm tracking-widest text-slate-950 focus:border-slate-500 focus:ring-slate-500" />
                <x-input-error :messages="$errors->updateWorkstationPin->get('pin')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="update_workstation_pin_confirmation" value="Confirm PIN" class="text-xs font-semibold uppercase tracking-wide text-slate-500" />
                <x-text-input id="update_workstation_pin_confirmation" name="pin_confirmation" type="tel" maxlength="4" pattern="\d{4}" inputmode="numeric" autocomplete="one-time-code" data-lpignore="true" data-1p-ignore class="mt-1 block w-full border-slate-300 font-mono text-sm tracking-widest text-slate-950 focus:border-slate-500 focus:ring-slate-500" />
                <x-input-error :messages="$errors->updateWorkstationPin->get('pin_confirmation')" class="mt-2" />
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                    Save PIN
                </button>

                @if (session('status') === 'workstation-pin-updated')
                    <p
                        x-data="{ show: true }"
                        x-show="show"
                        x-transition
                        x-init="setTimeout(() => show = false, 2000)"
                        class="text-sm text-slate-600"
                    >Saved.</p>
                @endif
            </div>
        </form>
    @endif
</section>

<script>
    document.querySelectorAll('.ops-profile-workstation-pin-form input[name="pin"], .ops-profile-workstation-pin-form input[name="pin_confirmation"]').forEach((field) => {
        field.addEventListener('input', (event) => {
            const input = event.currentTarget;
            input.value = String(input.value).replace(/\D/g, '').slice(0, 4);
        });
    });
</script>
