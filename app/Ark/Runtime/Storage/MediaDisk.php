<?php

namespace App\Ark\Runtime\Storage;

/**
 * Durable shop-media disk selector.
 *
 * Hosted cutover sets ARK_MEDIA_DISK=s3 (R2/S3-compatible). Default remains local.
 * Stores should migrate from hardcoded 'local' to MediaDisk::name() in a later pass.
 *
 * @see docs/deployment/ark-complete-hosted-storage-doctrine-v1.md
 */
final class MediaDisk
{
    public static function name(): string
    {
        $disk = (string) config('filesystems.media_disk', 'local');

        return $disk !== '' ? $disk : 'local';
    }
}
