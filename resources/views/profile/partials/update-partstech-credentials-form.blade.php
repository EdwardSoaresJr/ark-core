<section>
    <div class="mb-3">
        <div class="subheader">Integrations</div>
        <h2 class="h3 mb-1">PartsTech login</h2>
        <p class="text-secondary mb-0">
            Optional personal PartsTech seat. ARK prepares carts and pulls quotes with this login - sign into PartsTech in your browser as the same user. Leave blank to use the shop PartsTech account.
        </p>
    </div>

    <form method="post" action="{{ route('profile.partstech.update') }}">
        @csrf
        @method('patch')

        <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6">
                <label for="partstech_username" class="form-label">PartsTech username</label>
                <input
                    id="partstech_username"
                    name="partstech_username"
                    type="text"
                    @class(['form-control', 'is-invalid' => $errors->has('partstech_username')])
                    value="{{ old('partstech_username', $user->partstech_username) }}"
                    autocomplete="off"
                >
                @foreach ($errors->get('partstech_username') as $message)
                    <div class="invalid-feedback">{{ $message }}</div>
                @endforeach
            </div>

            <div class="col-12 col-sm-6">
                <label for="partstech_password" class="form-label">PartsTech password</label>
                <input
                    id="partstech_password"
                    name="partstech_password"
                    type="password"
                    @class(['form-control', 'is-invalid' => $errors->has('partstech_password')])
                    placeholder="{{ $user->hasStoredPartsTechPassword() ? 'Leave blank to keep current password' : 'Required with username' }}"
                    autocomplete="new-password"
                >
                @foreach ($errors->get('partstech_password') as $message)
                    <div class="invalid-feedback">{{ $message }}</div>
                @endforeach
            </div>
        </div>

        @if ($user->usesPersonalPartsTechLogin())
            <p class="text-secondary">Using personal login <strong>{{ $user->partstech_username }}</strong>. Clear username to revert to the shop default.</p>
        @endif

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                Save PartsTech login
            </button>

            @if (session('status') === 'partstech-updated')
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
</section>
