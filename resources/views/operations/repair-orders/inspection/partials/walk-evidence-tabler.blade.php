@if (count($photos ?? []) > 0)
    @php
        $canEditEvidence = (bool) ($canEdit ?? false);
        $evidenceSurface = $surface ?? null;
    @endphp
    <div class="row g-2 mb-3" data-inspection-walk-evidence>
        @foreach ($photos as $photo)
            <div class="col-4 col-sm-3">
                <div class="ratio ratio-1x1 rounded border overflow-hidden">
                    @if ($photo['is_video'] ?? false)
                        <a
                            href="{{ $photo['url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="d-block"
                            aria-label="Open video full size"
                        >
                            <video
                                src="{{ $photo['url'] }}"
                                muted
                                playsinline
                                preload="metadata"
                                class="w-100 h-100 object-fit-cover"
                            ></video>
                            <span class="badge bg-secondary text-secondary-fg position-absolute bottom-0 start-0 m-1">Open video</span>
                        </a>
                    @else
                        <button
                            type="button"
                            class="btn p-0 border-0 w-100 h-100"
                            data-ops-lightbox="{{ $photo['url'] }}"
                            data-ops-lightbox-alt="{{ $label ?? 'Inspection photo' }}"
                            aria-label="View photo larger"
                        >
                            <img
                                src="{{ $photo['url'] }}"
                                alt=""
                                class="w-100 h-100 object-fit-cover"
                            >
                        </button>
                    @endif
                </div>

                @if ($canEditEvidence && filled($photo['destroy_url'] ?? null))
                    <form
                        method="post"
                        action="{{ $photo['destroy_url'] }}"
                        class="mt-1"
                        onsubmit="return confirm('Remove this photo?');"
                        data-inspection-walk-evidence-delete
                    >
                        @csrf
                        @method('delete')
                        @if (filled($evidenceSurface))
                            <input type="hidden" name="surface" value="{{ $evidenceSurface }}">
                        @endif
                        <button
                            type="submit"
                            class="btn btn-sm btn-ghost-danger w-100"
                            aria-label="Remove photo"
                            title="Remove photo"
                        >
                            <x-tabler.icon name="trash" />
                            Remove
                        </button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
@endif
