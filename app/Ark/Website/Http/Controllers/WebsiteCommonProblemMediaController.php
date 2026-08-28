<?php

namespace App\Ark\Website\Http\Controllers;

use App\Ark\Operations\Leads\Public\CommonProblemFeaturedMedia;
use App\Ark\Operations\Leads\Public\CommonProblemRegistry;
use App\Ark\Operations\Leads\Public\PublicSurfaceSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class WebsiteCommonProblemMediaController
{
    public function index(): View
    {
        $stored = CommonProblemFeaturedMedia::allStored();

        $pages = collect(CommonProblemRegistry::all())
            ->map(function (array $problem) use ($stored): array {
                $slug = (string) $problem['slug'];
                $gallery = CommonProblemFeaturedMedia::galleryForDisplay($stored[$slug] ?? null);
                $primary = $gallery[0] ?? null;

                return [
                    'slug' => $slug,
                    'title' => (string) $problem['title'],
                    'photo_count' => count($gallery),
                    'has_media' => $gallery !== [],
                    'preview_url' => $primary['url'] ?? null,
                ];
            })
            ->sortBy('title')
            ->values()
            ->all();

        return view('website.page-media.index', [
            'pages' => $pages,
            'mediaCount' => collect($pages)->where('has_media', true)->count(),
        ]);
    }

    public function edit(string $slug): View
    {
        $problem = CommonProblemRegistry::find($slug);

        if ($problem === null) {
            abort(404);
        }

        $gallery = CommonProblemFeaturedMedia::forSlug($slug);

        $galleryItems = collect($gallery)
            ->map(function (array $item): array {
                return [
                    'id' => $item['id'],
                    'path' => $item['path'],
                    'alt' => $item['alt'],
                    'caption' => $item['caption'],
                    'preview_url' => PublicSurfaceSettings::photoUrl($item['path']),
                ];
            })
            ->values()
            ->all();

        return view('website.page-media.edit', [
            'problem' => $problem,
            'galleryItems' => $galleryItems,
            'publicUrl' => route('public.common-problems.show', $slug),
        ]);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $problem = CommonProblemRegistry::find($slug);

        if ($problem === null) {
            abort(404);
        }

        $existing = collect(CommonProblemFeaturedMedia::forSlug($slug))->keyBy('id');

        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'string', 'max:64'],
            'items.*.alt' => ['required', 'string', 'max:255'],
            'items.*.caption' => ['nullable', 'string', 'max:500'],
            'new_files' => ['nullable', 'array'],
            'new_files.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'new_alts' => ['nullable', 'array'],
            'new_alts.*' => ['nullable', 'string', 'max:255'],
            'new_captions' => ['nullable', 'array'],
            'new_captions.*' => ['nullable', 'string', 'max:500'],
        ]);

        $items = [];
        $orderedItems = is_array($data['items'] ?? null) ? $data['items'] : [];

        foreach ($orderedItems as $index => $itemInput) {
            $id = (string) ($itemInput['id'] ?? '');
            $existingItem = $existing->get($id);

            if ($existingItem === null) {
                continue;
            }

            $alt = trim((string) ($itemInput['alt'] ?? ''));

            if ($altError = CommonProblemFeaturedMedia::altTextError($alt)) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(['items.'.$index.'.alt' => $altError]);
            }

            $preserved = [
                'id' => $id,
                'path' => $existingItem['path'],
                'alt' => $alt,
                'caption' => trim((string) ($itemInput['caption'] ?? '')),
            ];

            foreach (['source_repair_order_id', 'source_media_id', 'contributed_by', 'contributed_at'] as $traceKey) {
                if (array_key_exists($traceKey, $existingItem)) {
                    $preserved[$traceKey] = $existingItem[$traceKey];
                }
            }

            $items[] = $preserved;
        }

        /** @var list<UploadedFile> $newFiles */
        $newFiles = $request->file('new_files', []) ?? [];
        $newAlts = is_array($data['new_alts'] ?? null) ? $data['new_alts'] : [];
        $newCaptions = is_array($data['new_captions'] ?? null) ? $data['new_captions'] : [];

        foreach ($newFiles as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $alt = trim((string) ($newAlts[$index] ?? ''));

            if ($altError = CommonProblemFeaturedMedia::altTextError($alt)) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(['new_alts.'.$index => $altError]);
            }

            $items[] = [
                'id' => Str::uuid()->toString(),
                'path' => CommonProblemFeaturedMedia::storeUpload($slug, $file),
                'alt' => $alt,
                'caption' => trim((string) ($newCaptions[$index] ?? '')),
            ];
        }

        CommonProblemFeaturedMedia::persistGalleryForSlug($slug, $items);

        return redirect()
            ->route('website.page-media.edit', $slug)
            ->with('status', $items === [] ? 'Featured media gallery cleared.' : 'Featured media gallery saved.');
    }
}
