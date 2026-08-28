@php
    use App\Ark\Operations\Learn\LearnArkMedia;
    use App\Ark\Operations\Learn\LearnArticleKey;

    $articleKey = LearnArticleKey::make($role, $article['slug']);
@endphp

<details class="ops-learn-media-manager" @if ($errors->has('slot') || $errors->has('file') || $errors->has('youtube_url')) open @endif>
    <summary class="ops-learn-media-manager__summary">Guide media — upload images, video, or YouTube</summary>

    @if (session('learn_media_saved'))
        <p class="ops-learn-media-manager__flash" role="status">{{ session('learn_media_saved') }}</p>
    @endif

    <div class="ops-learn-media-manager__body">
        <p class="ops-learn-media-manager__help">
            Slot names must match the guide markup:
            figure screenshots use the filename (e.g. <code>intake-recognition-band.png</code>);
            walkthrough video uses <code>video:main</code>;
            optional poster image uses its filename (e.g. <code>poster.jpg</code>).
        </p>

        <form
            method="POST"
            action="{{ route('operations.learn.media.store', ['role' => $role, 'article' => $article['slug']]) }}"
            enctype="multipart/form-data"
            class="ops-learn-media-manager__form"
        >
            @csrf

            <label class="ops-learn-media-manager__field">
                <span class="ops-learn-media-manager__label">Slot</span>
                <input
                    type="text"
                    name="slot"
                    value="{{ old('slot', 'video:main') }}"
                    required
                    class="ops-learn-media-manager__input"
                    placeholder="video:main"
                >
            </label>

            <label class="ops-learn-media-manager__field">
                <span class="ops-learn-media-manager__label">Type</span>
                <select name="kind" class="ops-learn-media-manager__input">
                    <option value="image" @selected(old('kind') === 'image')>Image</option>
                    <option value="video" @selected(old('kind', 'video') === 'video')>Video upload</option>
                    <option value="youtube" @selected(old('kind') === 'youtube')>YouTube embed</option>
                </select>
            </label>

            <label class="ops-learn-media-manager__field">
                <span class="ops-learn-media-manager__label">File</span>
                <input
                    type="file"
                    name="file"
                    accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime"
                    class="ops-learn-media-manager__input"
                >
            </label>

            <label class="ops-learn-media-manager__field">
                <span class="ops-learn-media-manager__label">YouTube URL or ID</span>
                <input
                    type="text"
                    name="youtube_url"
                    value="{{ old('youtube_url') }}"
                    class="ops-learn-media-manager__input"
                    placeholder="https://www.youtube.com/watch?v=…"
                >
            </label>

            @if ($errors->any())
                <ul class="ops-learn-media-manager__errors">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif

            <button type="submit" class="ops-learn-media-manager__submit">Save media</button>
        </form>

        @if ($articleMedia->isNotEmpty())
            <ul class="ops-learn-media-manager__list">
                @foreach ($articleMedia as $media)
                    <li class="ops-learn-media-manager__item">
                        <div class="ops-learn-media-manager__item-main">
                            <p class="ops-learn-media-manager__item-slot">{{ $media->slot }}</p>
                            <p class="ops-learn-media-manager__item-meta">
                                {{ ucfirst($media->kind) }}
                                @if ($media->original_name)
                                    · {{ $media->original_name }}
                                @elseif ($media->youtube_video_id)
                                    · {{ $media->youtube_video_id }}
                                @endif
                            </p>
                            @if ($media->isImage())
                                <img src="{{ LearnArkMedia::mediaUrl($media) }}" alt="" class="ops-learn-media-manager__thumb">
                            @elseif ($media->isYoutube())
                                <div class="ops-learn-media-manager__thumb ops-learn-media-manager__thumb--youtube">YouTube</div>
                            @elseif ($media->isVideo())
                                <div class="ops-learn-media-manager__thumb ops-learn-media-manager__thumb--video">MP4</div>
                            @endif
                        </div>
                        <form
                            method="POST"
                            action="{{ route('operations.learn.media.destroy', ['role' => $role, 'article' => $article['slug'], 'media' => $media]) }}"
                            onsubmit="return confirm('Remove this media from the guide?');"
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="ops-learn-media-manager__remove">Remove</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="ops-learn-media-manager__empty">No uploaded media for this guide yet.</p>
        @endif
    </div>
</details>
