<section>
    <div class="border-b border-slate-200 pb-2">
        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Appearance</p>
        <h2 class="text-base font-black text-slate-950">Accent and display theme</h2>
        <p class="mt-0.5 text-xs text-slate-500">
            Personalize ARK colors and light or dark mode without changing login identity.
        </p>
    </div>

    <form method="post" action="{{ route('profile.appearance.update') }}" class="mt-4 max-w-2xl space-y-4">
        @csrf
        @method('patch')

        <div>
            <x-input-label value="Accent color" class="text-xs font-semibold uppercase tracking-wide text-slate-500" />
            <p class="mt-1 text-sm text-slate-600">Personalizes the sidebar wash, top bar stripe, page title, active navigation, workspace tabs, avatar, and accent highlights across the app.</p>
            @php
                use App\Ark\Runtime\Preferences\AccentColor;
                use App\Ark\Runtime\Preferences\AccentTheme;

                $selectedAccentTheme = old('accent_theme', $user->accentTheme()->value);
                $customAccentColor = AccentColor::resolve(old('accent_color', $user->accent_color));
                $accentSwatchClass = [
                    'ark2' => 'bg-ark-cerulean-500',
                    'orange' => 'bg-orange-500',
                    'blue' => 'bg-blue-500',
                    'emerald' => 'bg-emerald-500',
                    'violet' => 'bg-violet-500',
                    'rose' => 'bg-ark-pink-500',
                    'amber' => 'bg-amber-500',
                    'sky' => 'bg-sky-500',
                    'teal' => 'bg-teal-500',
                ];
            @endphp
            <div
                class="mt-3 space-y-3"
                x-data="{ theme: @js($selectedAccentTheme), color: @js($customAccentColor) }"
            >
                <div class="ops-accent-picker">
                    @foreach ($accentThemes as $theme)
                        <label class="ops-accent-choice">
                            <input
                                type="radio"
                                name="accent_theme"
                                value="{{ $theme->value }}"
                                x-model="theme"
                                @checked($selectedAccentTheme === $theme->value)
                            >
                            @if ($theme === AccentTheme::Custom)
                                <span class="ops-accent-swatch" :style="{ backgroundColor: color }"></span>
                            @else
                                <span class="ops-accent-swatch {{ $accentSwatchClass[$theme->value] }}"></span>
                            @endif
                            <span>{{ $theme->label() }}</span>
                        </label>
                    @endforeach
                </div>

                <div
                    x-show="theme === 'custom'"
                    x-cloak
                    class="flex flex-wrap items-center gap-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-2"
                >
                    <label for="accent_color" class="text-sm font-medium text-slate-700">Your color</label>
                    <input
                        id="accent_color"
                        type="color"
                        name="accent_color"
                        class="ops-accent-color-input h-10 w-14 cursor-pointer rounded border border-slate-300 bg-white p-0.5"
                        x-model="color"
                        :disabled="theme !== 'custom'"
                    >
                    <span class="font-mono text-xs uppercase tracking-wide text-slate-500" x-text="color"></span>
                </div>
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('accent_theme')" />
            <x-input-error class="mt-2" :messages="$errors->get('accent_color')" />
        </div>

        <div>
            <x-input-label value="Display theme" class="text-xs font-semibold uppercase tracking-wide text-slate-500" />
            <p class="mt-1 text-sm text-slate-600">Light or dark mode for ARK and ARKademy. Match system follows your device.</p>
            <div class="mt-3 flex flex-wrap gap-3">
                @foreach ($displayThemes as $theme)
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input
                            type="radio"
                            name="display_theme"
                            value="{{ $theme->value }}"
                            @checked(old('display_theme', $user->displayTheme()->value) === $theme->value)
                            class="border-slate-300 text-slate-900 focus:ring-slate-500"
                        >
                        <span>{{ $theme->label() }}</span>
                    </label>
                @endforeach
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('display_theme')" />
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="min-h-10 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white hover:bg-slate-800">
                Save appearance
            </button>

            @if (session('status') === 'appearance-updated')
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
</section>
