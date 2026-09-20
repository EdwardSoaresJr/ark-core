@if (count($photos ?? []) > 0)
    @php
        $canEditEvidence = (bool) ($canEdit ?? false);
        $evidenceSurface = $surface ?? null;
    @endphp
    <div class="ops-inspection-walk__evidence-grid">
        @foreach ($photos as $photo)
            <div class="ops-inspection-walk__evidence-tile">
                @if ($photo['is_video'] ?? false)
                    <a
                        href="{{ $photo['url'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="ops-inspection-walk__video-link"
                        aria-label="Open video full size"
                    >
                        <video
                            src="{{ $photo['url'] }}"
                            muted
                            playsinline
                            preload="metadata"
                            class="ops-inspection-walk__video"
                        ></video>
                        <span class="ops-inspection-walk__video-label">Open video</span>
                    </a>
                @else
                    <button
                        type="button"
                        class="ops-inspection-walk__thumb-btn"
                        data-ops-lightbox="{{ $photo['url'] }}"
                        data-ops-lightbox-alt="{{ $label ?? 'Inspection photo' }}"
                        aria-label="View photo larger"
                    >
                        <img
                            src="{{ $photo['url'] }}"
                            alt=""
                            class="ops-inspection-walk__thumb"
                        >
                    </button>
                @endif

                @if ($canEditEvidence && filled($photo['destroy_url'] ?? null))
                    <form
                        method="post"
                        action="{{ $photo['destroy_url'] }}"
                        class="ops-inspection-walk__evidence-delete"
                        onsubmit="return confirm('Remove this photo?');"
                    >
                        @csrf
                        @method('delete')
                        @if (filled($evidenceSurface))
                            <input type="hidden" name="surface" value="{{ $evidenceSurface }}">
                        @endif
                        <button
                            type="submit"
                            class="ops-inspection-walk__evidence-delete-btn"
                            aria-label="Remove photo"
                            title="Remove photo"
                        >
                            ×
                        </button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
@endif
