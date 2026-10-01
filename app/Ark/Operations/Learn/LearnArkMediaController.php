<?php

namespace App\Ark\Operations\Learn;

use App\Ark\Runtime\Authorization\ArkCapability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LearnArkMediaController
{
    public function __construct(
        private readonly LearnArkMediaStore $store,
    ) {}

    public function store(Request $request, string $role, string $article): RedirectResponse
    {
        abort_unless($request->user()?->can(ArkCapability::SettingsManage->value), 403);

        $resolved = LearnArkCatalog::articleFor($request->user(), $role, $article);

        abort_if($resolved === null, 404);

        $articleKey = LearnArticleKey::make($role, $article);

        $validated = $request->validate([
            'slot' => ['required', 'string', 'max:120', 'regex:/^[a-zA-Z0-9._:-]+$/'],
            'kind' => ['required', Rule::in(['image', 'video', 'youtube'])],
            'file' => [
                Rule::requiredIf(in_array($request->input('kind'), ['image', 'video'], true)),
                'nullable',
                'file',
                'max:'.($request->input('kind') === 'video' ? 102400 : 20480),
            ],
            'youtube_url' => [
                Rule::requiredIf($request->input('kind') === 'youtube'),
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $kind = (string) $validated['kind'];
        $youtubeId = null;

        if ($kind === 'youtube') {
            $youtubeId = LearnYoutubeVideoId::parse($validated['youtube_url'] ?? '');

            if ($youtubeId === null) {
                return back()
                    ->withInput()
                    ->withErrors(['youtube_url' => 'Paste a valid YouTube link or 11-character video ID.']);
            }
        }

        if ($kind === 'image') {
            $request->validate([
                'file' => ['mimes:jpg,jpeg,png,webp,gif'],
            ]);
        }

        if ($kind === 'video') {
            $request->validate([
                'file' => ['mimetypes:video/mp4,video/webm,video/quicktime'],
            ]);
        }

        $this->store->replace(
            articleKey: $articleKey,
            slot: (string) $validated['slot'],
            kind: $kind,
            user: $request->user(),
            file: $request->file('file'),
            youtubeVideoId: $youtubeId,
        );

        return redirect()
            ->route('operations.learn.show', ['role' => $role, 'article' => $article])
            ->with('learn_media_saved', 'Media saved for this guide.');
    }

    public function destroy(Request $request, string $role, string $article, LearnArticleMedia $media): RedirectResponse
    {
        abort_unless($request->user()?->can(ArkCapability::SettingsManage->value), 403);

        $articleKey = LearnArticleKey::make($role, $article);

        abort_unless($media->article_key === $articleKey, 404);

        $this->store->destroy($media);

        return redirect()
            ->route('operations.learn.show', ['role' => $role, 'article' => $article])
            ->with('learn_media_saved', 'Media removed.');
    }
}
