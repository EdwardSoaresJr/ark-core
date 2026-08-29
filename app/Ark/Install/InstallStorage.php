<?php

namespace App\Ark\Install;

/**
 * Installer filesystem roots. PHPUnit uses an isolated subtree so Feature/Install
 * tests cannot poison (or wipe) a real local Herd/Docker install state.
 */
final class InstallStorage
{
    public static function path(string $relative): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $root = app()->environment('testing') ? 'install/testing' : 'install';

        return storage_path('app/'.$root.'/'.$relative);
    }
}
