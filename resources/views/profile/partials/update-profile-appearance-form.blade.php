<section>
    <div class="mb-3">
        <div class="subheader">Appearance</div>
        <h2 class="h3 mb-1">Accent and display theme</h2>
        <p class="text-secondary mb-0">
            Personalize ARK colors and light or dark mode without changing login identity.
        </p>
    </div>

    <form method="post" action="{{ route('profile.appearance.update') }}">
        @csrf
        @method('patch')

        @php
            use App\Ark\Runtime\Preferences\AccentColor;
            use App\Ark\Runtime\Preferences\AccentTheme;

            $selectedAccentTheme = old('accent_theme', $user->accentTheme()->value);
            $customAccentColor = AccentColor::resolve(old('accent_color', $user->accent_color));
        @endphp

        <fieldset class="mb-4">
            <legend class="form-label">Accent color</legend>
            <p class="text-secondary">Personalizes the sidebar wash, top bar stripe, page title, active navigation, workspace tabs, avatar, and accent highlights across the app.</p>
            <div
                class="d-flex flex-column gap-3"
                x-data="{ theme: @js($selectedAccentTheme), color: @js($customAccentColor) }"
            >
                <div class="form-selectgroup" data-profile-accent-picker>
                    @foreach ($accentThemes as $theme)
                        <label class="form-selectgroup-item">
                            <input
                                type="radio"
                                name="accent_theme"
                                value="{{ $theme->value }}"
                                class="form-selectgroup-input"
                                x-model="theme"
                                @checked($selectedAccentTheme === $theme->value)
                            >
                            <span class="form-selectgroup-label d-flex align-items-center gap-2">
                                @if ($theme === AccentTheme::Custom)
                                    <span class="avatar avatar-xs rounded-circle" :style="{ backgroundColor: color }" aria-hidden="true"></span>
                                @else
                                    {{-- data-accent sets --tblr-primary to this theme's color for the swatch only. --}}
                                    <span class="avatar avatar-xs rounded-circle bg-primary" data-accent="{{ $theme->value }}" aria-hidden="true"></span>
                                @endif
                                <span>{{ $theme->label() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div x-show="theme === 'custom'" x-cloak>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <label for="accent_color" class="form-label mb-0">Your color</label>
                        <input
                            id="accent_color"
                            type="color"
                            name="accent_color"
                            class="form-control form-control-color"
                            x-model="color"
                            :disabled="theme !== 'custom'"
                        >
                        <span class="font-monospace small text-uppercase text-secondary" x-text="color"></span>
                    </div>
                </div>
            </div>
            @foreach ([...$errors->get('accent_theme'), ...$errors->get('accent_color')] as $message)
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @endforeach
        </fieldset>

        <fieldset class="mb-3">
            <legend class="form-label">Display theme</legend>
            <p class="text-secondary">Light or dark mode for ARK and ARKademy. Match system follows your device.</p>
            <div>
                @foreach ($displayThemes as $theme)
                    <label class="form-check form-check-inline">
                        <input
                            type="radio"
                            name="display_theme"
                            value="{{ $theme->value }}"
                            @checked(old('display_theme', $user->displayTheme()->value) === $theme->value)
                            class="form-check-input"
                        >
                        <span class="form-check-label">{{ $theme->label() }}</span>
                    </label>
                @endforeach
            </div>
            @foreach ($errors->get('display_theme') as $message)
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @endforeach
        </fieldset>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                Save appearance
            </button>

            @if (session('status') === 'appearance-updated')
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
