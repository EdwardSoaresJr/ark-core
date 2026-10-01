@props([
    'role',
    'article',
    'file',
    'videoKey',
    'title',
    'posterFile' => null,
    'articleKey' => null,
])

@php
    use App\Ark\Operations\Learn\LearnArkMedia;

    $resolved = LearnArkMedia::resolveVideo($role, $article, $videoKey, $file, $posterFile);
    $hasVideo = $resolved['hasMedia'];
    $isYoutube = ($resolved['kind'] ?? null) === 'youtube';
    $src = $resolved['src'];
    $youtubeId = $resolved['youtubeId'];
    $poster = $resolved['poster'];
    $resolvedArticleKey = $articleKey ?? "{$role}:{$article}";
@endphp

<div
    class="ops-learn-video"
    x-data="arkLearnVideo({
        articleKey: @js($resolvedArticleKey),
        videoKey: @js($videoKey),
        videoUrl: @js(route('operations.learn.progress.video')),
        hasVideo: @js($hasVideo && ! $isYoutube),
    })"
    x-init="init()"
>
    <p class="ops-learn-video__title">{{ $title }}</p>

    @if ($isYoutube && $youtubeId)
        <div class="ops-learn-video__embed">
            <iframe
                src="https://www.youtube-nocookie.com/embed/{{ $youtubeId }}"
                title="{{ $title }}"
                loading="lazy"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowfullscreen
            ></iframe>
        </div>
    @elseif ($hasVideo && $src)
        <video
            class="ops-learn-video__player"
            controls
            playsinline
            preload="metadata"
            @if ($poster) poster="{{ $poster }}" @endif
            x-ref="player"
            @timeupdate="onTimeUpdate()"
            @pause="saveProgress()"
            @ended="markComplete()"
        >
            <source src="{{ $src }}" type="video/mp4">
        </video>
    @else
        <div class="ops-learn-video__placeholder">
            <span class="ops-learn-video__placeholder-label">Training video</span>
            <span class="ops-learn-video__placeholder-path">video:{{ $videoKey }}</span>
            <span class="ops-learn-video__placeholder-note">Upload MP4, or paste a YouTube link in Guide media.</span>
        </div>
    @endif

    <p class="ops-learn-video__status" x-show="statusMessage" x-text="statusMessage"></p>
</div>
