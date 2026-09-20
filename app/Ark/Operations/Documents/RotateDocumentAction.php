<?php

namespace App\Ark\Operations\Documents;

use App\Models\User;
use Imagick;
use ImagickException;
use ImagickPixel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Rotate pages 90°. Writes a new storage object and retargets the row —
 * never overwrites the previous storage_path in place.
 */
final class RotateDocumentAction
{
    public function __construct(
        private readonly DocumentStore $store,
        private readonly RecordDocumentEventAction $events,
    ) {}

    public function handle(Document $document, string $direction, User $actor): Document
    {
        if (! $document->isActive()) {
            throw ValidationException::withMessages([
                'document' => 'Retired documents cannot be rotated.',
            ]);
        }

        $degrees = match ($direction) {
            'left' => -90.0,
            'right' => 90.0,
            default => throw ValidationException::withMessages([
                'direction' => 'Choose rotate left or right.',
            ]),
        };

        if (! class_exists(Imagick::class)) {
            throw new RuntimeException('Document rotation requires Imagick on this host.');
        }

        if (! Storage::disk('local')->exists($document->storage_path)) {
            throw ValidationException::withMessages([
                'document' => 'Document file is missing.',
            ]);
        }

        $absolute = Storage::disk('local')->path($document->storage_path);
        $tempBase = sys_get_temp_dir().'/ark-doc-rotate-'.uniqid('', true);
        $writtenTemp = null;
        $oldPath = $document->storage_path;

        try {
            $image = new Imagick;
            try {
                $image->readImage($absolute);
                foreach ($image as $page) {
                    $page->rotateImage(new ImagickPixel('white'), $degrees);
                }

                if ($document->isPdf()) {
                    $image->setImageFormat('pdf');
                    $writtenTemp = $tempBase.'.pdf';
                    $image->writeImages($writtenTemp, true);
                    $extension = 'pdf';
                    $contentType = 'application/pdf';
                } else {
                    $format = match (true) {
                        str_contains($document->content_type, 'png') => 'png',
                        default => 'jpeg',
                    };
                    $image->setImageFormat($format);
                    $writtenTemp = $tempBase.'.'.$format;
                    $image->writeImage($writtenTemp);
                    $extension = $format === 'jpeg' ? 'jpg' : $format;
                    $contentType = $format === 'png' ? 'image/png' : 'image/jpeg';
                }
            } catch (ImagickException $e) {
                throw ValidationException::withMessages([
                    'document' => 'This file could not be rotated.',
                ]);
            } finally {
                $image->clear();
                $image->destroy();
            }

            return DB::transaction(function () use (
                $document,
                $actor,
                $direction,
                $degrees,
                $writtenTemp,
                $extension,
                $contentType,
                $oldPath,
            ): Document {
                $locked = Document::query()->lockForUpdate()->findOrFail($document->id);
                $stored = $this->store->storeAbsoluteFile(
                    (int) $locked->customer_id,
                    (string) $writtenTemp,
                    $extension,
                    $contentType,
                    $locked->original_name,
                    $locked->page_count,
                );

                $locked->storage_path = $stored['storage_path'];
                $locked->content_type = $stored['content_type'];
                $locked->byte_size = $stored['byte_size'];
                $locked->save();

                $this->events->handle($locked, DocumentEventType::Rotated, $actor, [
                    'direction' => $direction,
                    'degrees' => $degrees,
                ]);

                if ($oldPath !== '' && $oldPath !== $locked->storage_path && Storage::disk('local')->exists($oldPath)) {
                    Storage::disk('local')->delete($oldPath);
                }

                return $locked->fresh() ?? $locked;
            });
        } finally {
            foreach (glob($tempBase.'*') ?: [] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }
}
