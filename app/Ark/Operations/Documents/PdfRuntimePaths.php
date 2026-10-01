<?php

namespace App\Ark\Operations\Documents;

final class PdfRuntimePaths
{
    /**
     * @return array{node: ?string, npm: ?string, chrome: ?string, include_path: ?string}
     */
    public static function discover(): array
    {
        $node = self::resolveNodeBinary();
        $npm = self::resolveNpmBinary($node);
        $chrome = self::resolveChromePath();

        return [
            'node' => $node,
            'npm' => $npm,
            'chrome' => $chrome,
            'include_path' => $node ? dirname($node) : null,
        ];
    }

    public static function resolveNodeBinary(): ?string
    {
        if ($configured = self::executable(env('PDF_NODE_BINARY'))) {
            return $configured;
        }

        $herdVersions = self::herdNvmVersionsDirectory();

        if ($herdVersions !== null) {
            $herdNode = self::latestBinaryInVersionedDirectory($herdVersions, 'node');

            if ($herdNode !== null) {
                return $herdNode;
            }
        }

        foreach (['/usr/bin/node', '/usr/local/bin/node', '/opt/homebrew/bin/node'] as $candidate) {
            if ($executable = self::executable($candidate)) {
                return $executable;
            }
        }

        return null;
    }

    public static function resolveNpmBinary(?string $nodeBinary = null): ?string
    {
        if ($configured = self::executable(env('PDF_NPM_BINARY'))) {
            return $configured;
        }

        $nodeBinary ??= self::resolveNodeBinary();

        if ($nodeBinary !== null) {
            $sibling = dirname($nodeBinary).'/npm';

            if ($executable = self::executable($sibling)) {
                return $executable;
            }
        }

        foreach (['/usr/bin/npm', '/usr/local/bin/npm', '/opt/homebrew/bin/npm'] as $candidate) {
            if ($executable = self::executable($candidate)) {
                return $executable;
            }
        }

        return null;
    }

    public static function resolveChromePath(): ?string
    {
        if ($configured = self::executable(env('PDF_CHROME_PATH'))) {
            return $configured;
        }

        $cacheDirectories = array_values(array_unique(array_filter([
            env('PUPPETEER_CACHE_DIR'),
            '/app/puppeteer-cache',
            self::homeDirectory() !== null ? self::homeDirectory().'/.cache/puppeteer' : null,
            '/root/.cache/puppeteer',
        ])));

        foreach ($cacheDirectories as $cacheDirectory) {
            if ($chrome = self::findChromeInCacheDirectory($cacheDirectory)) {
                return $chrome;
            }
        }

        return null;
    }

    public static function herdNvmVersionsDirectory(): ?string
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            return null;
        }

        $home = self::homeDirectory();

        if ($home === null) {
            return null;
        }

        $directory = $home.'/Library/Application Support/Herd/config/nvm/versions/node';

        return is_dir($directory) ? $directory : null;
    }

    public static function latestBinaryInVersionedDirectory(string $versionsDirectory, string $binaryName): ?string
    {
        $versionDirectories = glob($versionsDirectory.'/*', GLOB_ONLYDIR) ?: [];

        if ($versionDirectories === []) {
            return null;
        }

        usort(
            $versionDirectories,
            static fn (string $left, string $right): int => version_compare(basename($right), basename($left)),
        );

        foreach ($versionDirectories as $versionDirectory) {
            $candidate = $versionDirectory.'/bin/'.$binaryName;

            if ($executable = self::executable($candidate)) {
                return $executable;
            }
        }

        return null;
    }

    private static function findChromeInCacheDirectory(string $cacheDirectory): ?string
    {
        if (! is_dir($cacheDirectory)) {
            return null;
        }

        $headlessPatterns = [
            $cacheDirectory.'/chrome-headless-shell/*/chrome-headless-shell-linux64/chrome-headless-shell',
            $cacheDirectory.'/chrome-headless-shell/*/chrome-headless-shell',
        ];

        foreach ($headlessPatterns as $pattern) {
            $headlessShellMatches = glob($pattern, GLOB_NOSORT) ?: [];

            if ($headlessShellMatches !== []) {
                $found = self::pickNewestExecutable($headlessShellMatches);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        $macMatches = glob(
            $cacheDirectory.'/chrome/mac_*/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing',
            GLOB_NOSORT,
        ) ?: [];

        if ($macMatches !== []) {
            return self::pickNewestExecutable($macMatches);
        }

        $macIntelMatches = glob(
            $cacheDirectory.'/chrome/mac_*/chrome-mac-x64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing',
            GLOB_NOSORT,
        ) ?: [];

        if ($macIntelMatches !== []) {
            return self::pickNewestExecutable($macIntelMatches);
        }

        return null;
    }

    /**
     * @param  list<string>  $candidates
     */
    private static function pickNewestExecutable(array $candidates): ?string
    {
        $newest = null;
        $newestMtime = 0;

        foreach ($candidates as $candidate) {
            if (! is_executable($candidate)) {
                continue;
            }

            $mtime = filemtime($candidate) ?: 0;

            if ($mtime >= $newestMtime) {
                $newest = $candidate;
                $newestMtime = $mtime;
            }
        }

        return $newest;
    }

    private static function homeDirectory(): ?string
    {
        foreach ([env('HOME'), getenv('HOME') ?: null, $_SERVER['HOME'] ?? null] as $home) {
            if (is_string($home) && $home !== '') {
                return $home;
            }
        }

        if (! function_exists('posix_geteuid') || ! function_exists('posix_getpwuid')) {
            return null;
        }

        $account = posix_getpwuid(posix_geteuid());

        if (! is_array($account)) {
            return null;
        }

        $home = $account['dir'] ?? null;

        return is_string($home) && $home !== '' ? $home : null;
    }

    private static function executable(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        return is_executable($path) ? $path : null;
    }
}
