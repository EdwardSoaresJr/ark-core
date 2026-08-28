@php
    use App\Ark\Operations\Settings\OperationalProfile;
    $currentProfile = $settings->operationalProfile();
@endphp

<div class="border-t border-slate-200 pt-3 mt-3">
    <div class="border-b border-slate-200 pb-3">
        <h4 class="text-xs font-black uppercase tracking-[0.08em] text-slate-700">Shop profile</h4>
        <p class="mt-0.5 text-xs text-slate-500">
            Defaults only — not a different product. Applying a profile updates appointments, Check In visit mode, printing, and training gate.
            Staff-based solo behavior still comes from who is on the roster.
        </p>
    </div>

    <form method="POST" action="{{ route('operations.settings.shop.operational-profile.apply') }}" class="mt-3 space-y-3">
        @csrf
        <div class="grid gap-2 sm:grid-cols-3">
            @foreach (OperationalProfile::cases() as $profile)
                <label class="flex cursor-pointer flex-col border border-slate-300 bg-white p-3 hover:border-slate-500 {{ $currentProfile === $profile ? 'ring-2 ring-slate-900' : '' }}">
                    <span class="flex items-center gap-2">
                        <input
                            type="radio"
                            name="operational_profile"
                            value="{{ $profile->value }}"
                            @checked(old('operational_profile', $currentProfile?->value) === $profile->value)
                            class="border-slate-400 text-slate-950 focus:ring-slate-900"
                            required
                        >
                        <span class="text-xs font-bold text-slate-900">{{ $profile->label() }}</span>
                    </span>
                    <span class="mt-1.5 text-[11px] leading-snug text-slate-600">{{ $profile->summary() }}</span>
                </label>
            @endforeach
        </div>

        @error('operational_profile')
            <p class="text-xs font-semibold text-rose-700">{{ $message }}</p>
        @enderror

        <button type="submit" class="bg-slate-950 px-3 py-2 text-xs font-bold uppercase tracking-wide text-white hover:bg-slate-800">
            Apply profile defaults
        </button>
    </form>
</div>
