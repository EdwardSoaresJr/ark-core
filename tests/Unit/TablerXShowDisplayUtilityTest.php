<?php

use Symfony\Component\Finder\Finder;

test('no view hides an element with x-show while a Bootstrap display utility forces it visible', function (): void {
    // Bootstrap's d-* display utilities are !important, so plain x-show can never hide that element.
    $tag = '/<([a-zA-Z][\w.:-]*)((?:\s+[^\s=>"\'\/]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>"\']+))?)*)\s*\/?>/s';
    $display = '/^d-(block|flex|inline|inline-block|inline-flex|grid|inline-grid|table|table-cell|table-row)$/';
    $offenders = [];

    foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
        $contents = $file->getContents();
        preg_match_all($tag, $contents, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[2] as [$attributes, $offset]) {
            if (! preg_match('/(^|\s)x-show\s*=/', $attributes) || ! preg_match('/(?<![:\w-])class="([^"]*)"/', $attributes, $class)) {
                continue;
            }

            foreach (preg_split('/\s+/', trim($class[1])) as $name) {
                if (preg_match($display, $name)) {
                    $offenders[] = $file->getRelativePathname().':'.(substr_count(substr($contents, 0, $offset), "\n") + 1)." ({$name})";
                }
            }
        }
    }

    expect($offenders)->toBe([], 'Use x-show.important or move x-show to a wrapper: '.implode(', ', $offenders));
});
