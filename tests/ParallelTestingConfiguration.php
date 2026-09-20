<?php

namespace Tests;

use Illuminate\Support\Facades\ParallelTesting;

final class ParallelTestingConfiguration
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        ParallelTesting::setUpProcess(function (int|string|false $token): void {
            if ($token === false) {
                return;
            }

            $suffix = 'worker-'.$token;

            config([
                'filesystems.disks.local.root' => storage_path('framework/testing/disks/local/'.$suffix),
                'filesystems.disks.public.root' => storage_path('framework/testing/disks/public/'.$suffix),
            ]);

            foreach ([
                storage_path('framework/testing/disks/local/'.$suffix),
                storage_path('framework/testing/disks/public/'.$suffix),
            ] as $directory) {
                if (! is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }
            }
        });

        ParallelTesting::tearDownProcess(function (int|string|false $token): void {
            if ($token === false) {
                return;
            }

            $suffix = 'worker-'.$token;

            foreach ([
                storage_path('framework/testing/disks/local/'.$suffix),
                storage_path('framework/testing/disks/public/'.$suffix),
            ] as $directory) {
                if (! is_dir($directory)) {
                    continue;
                }

                $iterator = new RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST,
                );

                foreach ($iterator as $item) {
                    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
                }
            }
        });
    }
}
