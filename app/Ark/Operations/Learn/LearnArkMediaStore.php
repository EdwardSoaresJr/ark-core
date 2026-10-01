<?php

namespace App\Ark\Operations\Learn;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LearnArkMediaStore
{
    public function replace(
        string $articleKey,
        string $slot,
        string $kind,
        User $user,
        ?UploadedFile $file = null,
        ?string $youtubeVideoId = null,
    ): LearnArticleMedia {
        $existing = LearnArticleMedia::query()
            ->where('article_key', $articleKey)
            ->where('slot', $slot)
            ->first();

        if ($existing !== null) {
            $this->deleteFile($existing);
            $existing->delete();
        }

        $storagePath = null;
        $mimeType = null;
        $originalName = null;

        if ($file !== null) {
            $extension = $file->getClientOriginalExtension() ?: 'bin';
            $directory = 'learn-media/'.str_replace(':', '/', $articleKey);
            $filename = Str::uuid()->toString().'.'.strtolower($extension);
            $storagePath = $file->storeAs($directory, $filename, 'local');
            $mimeType = $file->getMimeType() ?: $file->getClientMimeType();
            $originalName = $file->getClientOriginalName();
        }

        return LearnArticleMedia::query()->create([
            'article_key' => $articleKey,
            'slot' => $slot,
            'kind' => $kind,
            'storage_path' => $storagePath,
            'mime_type' => $mimeType,
            'youtube_video_id' => $youtubeVideoId,
            'original_name' => $originalName,
            'uploaded_by_user_id' => $user->id,
        ]);
    }

    public function destroy(LearnArticleMedia $media): void
    {
        $this->deleteFile($media);
        $media->delete();
    }

    private function deleteFile(LearnArticleMedia $media): void
    {
        if ($media->storage_path === null || $media->storage_path === '') {
            return;
        }

        Storage::disk('local')->delete($media->storage_path);
    }
}
