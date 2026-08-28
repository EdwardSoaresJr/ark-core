<?php

use App\Ark\Operations\Documents\PdfRuntimePaths;
use Tests\TestCase;

uses(TestCase::class);

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
