<x-cloud.shell title="Choose your workspace" :footer="false">
    <x-cloud.trial-frame :step="$step" title="Choose your workspace">
        <p class="mt-3 text-[var(--cloud-muted)]">
            This is where you and your team will work every day.
        </p>

        <form method="post" action="{{ \App\Ark\Platform\Cloud\CloudUrls::route('trial.workspace.store') }}" class="mt-8 space-y-6"
              x-data="{ slug: @js(old('slug', $slug)) }">
            @csrf
            <div>
                <label for="slug" class="block text-sm font-semibold text-[var(--cloud-ink-soft)] mb-2">
                    Workspace address
                </label>
                <div class="flex items-stretch rounded-[0.65rem] border border-[var(--cloud-line)] bg-white overflow-hidden focus-within:border-[rgba(0,153,204,0.65)] focus-within:shadow-[0_0_0_4px_var(--cloud-glow)]">
                    <input
                        id="slug"
                        name="slug"
                        type="text"
                        x-model="slug"
                        required
                        pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                        class="flex-1 border-0 bg-transparent px-4 py-3.5 text-lg outline-none"
                        autocomplete="off"
                    >
                    <span class="flex items-center px-4 text-[var(--cloud-muted)] bg-[var(--cloud-paper)] border-l border-[var(--cloud-line)] text-sm sm:text-base whitespace-nowrap">
                        .arksms.com
                    </span>
                </div>
                @error('slug')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-3 text-sm text-[var(--cloud-muted)]">
                    This will be your workspace address for the free trial.
                </p>
                <p class="mt-1 text-sm text-[var(--cloud-muted)]">
                    Preview: <span class="font-medium text-[var(--cloud-ink-soft)]" x-text="slug + '.arksms.com'"></span>
                </p>
            </div>

            <div class="flex items-center justify-between gap-3 pt-2">
                <a href="{{ \App\Ark\Platform\Cloud\CloudUrls::route('trial.shop') }}" class="cloud-btn-ghost !py-3 !px-5">Back</a>
                <button type="submit" class="cloud-btn-primary !px-8 !py-3.5">Continue</button>
            </div>
        </form>
    </x-cloud.trial-frame>
</x-cloud.shell>
