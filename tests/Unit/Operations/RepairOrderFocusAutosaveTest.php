<?php

use Illuminate\Support\Facades\Process;

test('repair order focus return does not autosave or show saving', function (): void {
    $result = Process::run([
        'node',
        '--test',
        base_path('resources/js/ark-worksheet-focus-save.test.js'),
    ]);

    if (! $result->successful()) {
        $this->fail($result->errorOutput()."\n".$result->output());
    }

    expect($result->exitCode())->toBe(0);
});
