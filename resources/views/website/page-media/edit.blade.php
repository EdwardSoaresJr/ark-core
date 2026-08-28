<x-operations.app title="Website — Featured media">
    <section class="space-y-3">
        <div class="border border-slate-300 bg-white">
            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Website</p>
                <h1 class="mt-0.5 text-lg font-black text-slate-950">{{ $problem['title'] }}</h1>
                <p class="mt-1 text-xs text-slate-500">
                    Featured media gallery for <span class="font-mono">{{ $problem['slug'] }}</span>
                </p>
            </div>

            <div class="px-3 py-3">
                @include('website.partials.subnav', ['activeTab' => 'page-media'])

                <p class="mt-3">
                    <a href="{{ route('website.page-media.index') }}" class="text-xs font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]">← All pages</a>
                    <span class="mx-2 text-slate-300" aria-hidden="true">·</span>
                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="text-xs font-semibold text-[#0099cc] no-underline hover:text-[#0088b8]">View public page ↗</a>
                </p>

                @if (session('status'))
                    <p class="mt-3 border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900">{{ session('status') }}</p>
                @endif

                <form
                    method="POST"
                    action="{{ route('website.page-media.update', $problem['slug']) }}"
                    enctype="multipart/form-data"
                    class="mt-5 max-w-3xl space-y-5 border-t border-slate-200 pt-4"
                    x-data="{
                        items: @js($galleryItems),
                        newUploads: [],
                        dragIndex: null,
                        addFiles(event) {
                            Array.from(event.target.files || []).forEach((file) => {
                                this.newUploads.push({
                                    key: crypto.randomUUID(),
                                    file,
                                    alt: '',
                                    caption: '',
                                    preview: URL.createObjectURL(file),
                                });
                            });
                            event.target.value = '';
                        },
                        removeExisting(index) {
                            this.items.splice(index, 1);
                        },
                        removeNew(index) {
                            const upload = this.newUploads[index];
                            if (upload?.preview) {
                                URL.revokeObjectURL(upload.preview);
                            }
                            this.newUploads.splice(index, 1);
                        },
                        onDragStart(index) {
                            this.dragIndex = index;
                        },
                        onDrop(index) {
                            if (this.dragIndex === null || this.dragIndex === index) {
                                this.dragIndex = null;
                                return;
                            }
                            const moved = this.items.splice(this.dragIndex, 1)[0];
                            this.items.splice(index, 0, moved);
                            this.dragIndex = null;
                        },
                        submitGallery(event) {
                            const form = event.target;
                            form.querySelectorAll('[data-gallery-upload]').forEach((element) => element.remove());

                            this.newUploads.forEach((upload, index) => {
                                if (! upload.file) {
                                    return;
                                }

                                const transfer = new DataTransfer();
                                transfer.items.add(upload.file);
                                const input = document.createElement('input');
                                input.type = 'file';
                                input.name = 'new_files[' + index + ']';
                                input.hidden = true;
                                input.files = transfer.files;
                                input.dataset.galleryUpload = '1';
                                form.appendChild(input);
                            });
                        },
                    }"
                    @submit="submitGallery($event)"
                >
                    @csrf
                    @method('PATCH')

                    <div>
                        <h2 class="text-sm font-black text-slate-950">Gallery</h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Real shop photos only. The first image is primary for search previews. Public pages rotate through the gallery on each visit.
                        </p>

                        <div class="mt-3 space-y-3" x-show="items.length > 0">
                            <template x-for="(item, index) in items" :key="item.id">
                                <div
                                    class="flex flex-wrap gap-3 rounded-md border border-slate-200 bg-slate-50 p-3"
                                    draggable="true"
                                    @dragstart="onDragStart(index)"
                                    @dragover.prevent
                                    @drop.prevent="onDrop(index)"
                                >
                                    <div class="flex w-28 shrink-0 flex-col items-center gap-2">
                                        <span class="cursor-grab text-xs font-bold uppercase tracking-wide text-slate-400">Drag</span>
                                        <img :src="item.preview_url" alt="" class="aspect-video w-full rounded object-cover ring-1 ring-slate-200">
                                        <span class="text-[10px] font-semibold text-slate-500" x-show="index === 0">Primary</span>
                                    </div>

                                    <div class="min-w-0 flex-1 space-y-2">
                                        <input type="hidden" :name="'items[' + index + '][id]'" :value="item.id">

                                        <div>
                                            <label class="block text-xs font-semibold text-slate-900">ALT text</label>
                                            <input
                                                type="text"
                                                :name="'items[' + index + '][alt]'"
                                                x-model="item.alt"
                                                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm"
                                                minlength="25"
                                                required
                                            >
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-slate-900">Caption <span class="font-normal text-slate-500">(optional)</span></label>
                                            <input
                                                type="text"
                                                :name="'items[' + index + '][caption]'"
                                                x-model="item.caption"
                                                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm"
                                            >
                                        </div>

                                        <button
                                            type="button"
                                            class="text-xs font-semibold text-rose-700 hover:text-rose-900"
                                            @click="removeExisting(index)"
                                        >
                                            Remove image
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <p class="mt-3 text-xs text-slate-500" x-show="items.length === 0">
                            No photos yet. Upload real repair images below.
                        </p>
                    </div>

                    <div class="border-t border-slate-200 pt-4">
                        <h2 class="text-sm font-black text-slate-950">Add photos</h2>
                        <p class="mt-1 text-xs text-slate-500">JPG, PNG, or WebP up to 5 MB each. ALT text is required for every upload.</p>

                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            class="mt-2 block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-800"
                            @change="addFiles($event)"
                        >

                        <div class="mt-3 space-y-3" x-show="newUploads.length > 0">
                            <template x-for="(upload, index) in newUploads" :key="upload.key">
                                <div class="flex flex-wrap gap-3 rounded-md border border-dashed border-slate-300 bg-white p-3">
                                    <img :src="upload.preview" alt="" class="aspect-video w-28 rounded object-cover ring-1 ring-slate-200">

                                    <div class="min-w-0 flex-1 space-y-2">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-900">ALT text</label>
                                            <input
                                                type="text"
                                                :name="'new_alts[' + index + ']'"
                                                x-model="upload.alt"
                                                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm"
                                                minlength="25"
                                                required
                                            >
                                        </div>

                                        <div>
                                            <label class="block text-xs font-semibold text-slate-900">Caption <span class="font-normal text-slate-500">(optional)</span></label>
                                            <input
                                                type="text"
                                                :name="'new_captions[' + index + ']'"
                                                x-model="upload.caption"
                                                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm"
                                            >
                                        </div>

                                        <button
                                            type="button"
                                            class="text-xs font-semibold text-rose-700 hover:text-rose-900"
                                            @click="removeNew(index)"
                                        >
                                            Remove upload
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        @error('new_files.*')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                        @error('new_alts.*')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                        @error('items.*.alt')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-4">
                        <button type="submit" class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Save gallery
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</x-operations.app>
