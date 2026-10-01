<?php

use App\Ark\Operations\Documents\PdfRuntimePaths;

it('picks the newest node version directory', function (): void {
    $base = sys_get_temp_dir().'/pdf-runtime-'.uniqid('', true);
    $versions = $base.'/versions/node';

    foreach (['v20.0.0', 'v22.22.1'] as $version) {
        $bin = $versions.'/'.$version.'/bin';
        mkdir($bin, 0777, true);
        touch($bin.'/node');
        chmod($bin.'/node', 0755);
    }

    $resolved = PdfRuntimePaths::latestBinaryInVersionedDirectory($versions, 'node');

    expect($resolved)->toEndWith('v22.22.1/bin/node');

    @unlink($versions.'/v20.0.0/bin/node');
    @unlink($versions.'/v22.22.1/bin/node');
    @rmdir($versions.'/v20.0.0/bin');
    @rmdir($versions.'/v20.0.0');
    @rmdir($versions.'/v22.22.1/bin');
    @rmdir($versions.'/v22.22.1');
    @rmdir($versions);
    @rmdir(dirname($versions));
    @rmdir($base);
});

it('resolveNodeBinary tolerates missing herd nvm directory', function (): void {
    PdfRuntimePaths::resolveNodeBinary();

    expect(true)->toBeTrue();
});

it('discovers an executable node binary after application boot', function (): void {
    $node = config('services.pdf.node_binary');

    if ($node === null) {
        expect(PdfRuntimePaths::resolveNodeBinary())->toBeNull();

        return;
    }

    expect($node)->toBeString()->and(is_executable($node))->toBeTrue();
});

it('discovers chrome-headless-shell from a linux puppeteer cache layout', function (): void {
    $cache = sys_get_temp_dir().'/pdf-chrome-'.uniqid('', true);
    $dir = $cache.'/chrome-headless-shell/linux-1.2.3/chrome-headless-shell-linux64';
    mkdir($dir, 0777, true);
    $chrome = $dir.'/chrome-headless-shell';
    file_put_contents($chrome, "#!/bin/sh\n");
    chmod($chrome, 0755);

    $previousCache = getenv('PUPPETEER_CACHE_DIR');
    $previousChrome = getenv('PDF_CHROME_PATH');

    putenv('PUPPETEER_CACHE_DIR='.$cache);
    putenv('PDF_CHROME_PATH');
    $_ENV['PUPPETEER_CACHE_DIR'] = $cache;
    unset($_ENV['PDF_CHROME_PATH'], $_SERVER['PDF_CHROME_PATH']);

    try {
        expect(PdfRuntimePaths::resolveChromePath())->toBe($chrome);
    } finally {
        if (is_string($previousCache) && $previousCache !== '') {
            putenv('PUPPETEER_CACHE_DIR='.$previousCache);
            $_ENV['PUPPETEER_CACHE_DIR'] = $previousCache;
        } else {
            putenv('PUPPETEER_CACHE_DIR');
            unset($_ENV['PUPPETEER_CACHE_DIR']);
        }

        if (is_string($previousChrome) && $previousChrome !== '') {
            putenv('PDF_CHROME_PATH='.$previousChrome);
            $_ENV['PDF_CHROME_PATH'] = $previousChrome;
        }

        @unlink($chrome);
        @rmdir($dir);
        @rmdir(dirname($dir));
        @rmdir(dirname($dir, 2));
        @rmdir($cache);
    }
});
