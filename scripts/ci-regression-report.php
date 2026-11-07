<?php

/*
 * Checks a full-suite run against the committed known-failure baseline (.github/regression-known-failures.txt).
 *
 *   php scripts/ci-regression-report.php --junit=<junit.xml> --known=<baseline.txt> --out=<failures.txt> [--exit-code=<n>] [--files=<list.txt>]
 *
 * Without --files it checks the full suite (nightly, release gates). With --files (one test file path per line, as
 * pull-request CI selects them) it checks that subset: only baseline entries from those files count, and the run is
 * complete when every listed file reported at least one test.
 *
 * Writes this run's failing tests (one "tests/...Test.php::test name" per line) to --out and a Markdown report to
 * stdout. A failure is a regression until it is fixed or explicitly added to the baseline; the previous run is never
 * an implicit baseline. Exits 1 when:
 *   - a test fails that the baseline does not list;
 *   - the run crashed or was stopped (--exit-code other than 0, 1 or 2, or not a number) or left no readable JUnit report;
 *   - the run is incomplete: fewer tests than 90% of the baseline's "# tests:" count, or with --files a listed file
 *     reported no test;
 *   - the baseline file is missing.
 * Baseline entries that now pass are reported for removal and do not fail the run.
 */

$options = getopt('', ['junit:', 'known:', 'out:', 'exit-code::', 'files:']);
foreach (['junit', 'known', 'out'] as $required) {
    if (! is_string($options[$required] ?? null)) {
        fwrite(STDERR, "usage: php scripts/ci-regression-report.php --junit=<junit.xml> --known=<baseline.txt> --out=<failures.txt> [--exit-code=<n>] [--files=<list.txt>]\n");
        exit(2);
    }
}

$problems = [];
$files = null;
if (isset($options['files'])) {
    if (! is_string($options['files']) || ! is_file($options['files'])) {
        $problems[] = 'The list of selected test files is missing ('.(is_string($options['files']) ? $options['files'] : '').').';
        $files = [];
    } else {
        $files = array_values(array_unique(array_filter(array_map('trim', file($options['files'], FILE_IGNORE_NEW_LINES) ?: []), fn (string $line): bool => $line !== '')));
        if ($files === []) {
            $problems[] = 'The list of selected test files is empty.';
        }
    }
}
$exitCode = isset($options['exit-code']) ? trim((string) $options['exit-code']) : null;
// Pest and PHPUnit exit 0 when every test passes, 1 when a test fails and 2 when a test errors; anything else (255 for
// a PHP fatal error, 137 or 143 when killed) means the run itself broke.
if ($exitCode !== null && ! in_array($exitCode, ['0', '1', '2'], true)) {
    $problems[] = ctype_digit($exitCode)
        ? "The suite exited with code {$exitCode}: it crashed or was stopped before finishing."
        : 'The suite recorded no exit code: it was stopped or timed out before finishing.';
}

$failing = [];
$total = 0;
$reportedFiles = [];
$xml = is_file($options['junit']) ? @simplexml_load_file($options['junit']) : false;
if ($xml === false) {
    $problems[] = "The suite left no readable JUnit report ({$options['junit']}).";
} else {
    foreach ($xml->xpath('//testcase') ?: [] as $case) {
        $total++;
        $file = (string) ($case['file'] ?? '');
        $reportedFiles[explode('::', $file, 2)[0]] = true;
        if (isset($case->failure) || isset($case->error)) {
            $failing[] = str_contains($file, '::') ? $file : $file.'::'.(string) $case['name'];
        }
    }
    if ($total === 0) {
        $problems[] = 'The JUnit report lists no tests.';
    }
}
$failing = array_values(array_unique($failing));
sort($failing);
file_put_contents($options['out'], $failing === [] ? '' : implode("\n", $failing)."\n");

$known = [];
$expectedTests = null;
if (! is_file($options['known'])) {
    $problems[] = "The known-failure baseline is missing ({$options['known']}).";
} else {
    foreach (file($options['known'], FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if (preg_match('/^#\s*tests:\s*(\d+)/i', $line, $match) === 1) {
            $expectedTests = (int) $match[1];
        } elseif ($line !== '' && ! str_starts_with($line, '#')) {
            $known[] = $line;
        }
    }
}
if ($files !== null) {
    // A subset run: the baseline's suite count does not apply, and only its entries from the selected files can occur.
    $known = array_values(array_filter($known, fn (string $test): bool => in_array(explode('::', $test, 2)[0], $files, true)));
    $silent = array_values(array_filter($files, fn (string $file): bool => ! isset($reportedFiles[$file])));
    if ($xml !== false && $total > 0 && $silent !== []) {
        $problems[] = count($silent).' of '.count($files).' selected test files reported no test ('.implode(', ', array_slice($silent, 0, 5)).(count($silent) > 5 ? ', …' : '').'). The run is incomplete.';
    }
} elseif ($xml !== false && $total > 0 && $expectedTests !== null && $total < (int) floor($expectedTests * 0.9)) {
    $problems[] = "Only {$total} tests ran; the baseline expects about {$expectedTests}. The run is incomplete.";
}

$new = array_values(array_diff($failing, $known));
$stillFailing = array_values(array_intersect($failing, $known));
// Only a complete run can show that a known failure passes; in a partial run it may simply not have run.
$nowPassing = $problems === [] ? array_values(array_diff($known, $failing)) : [];

$list = function (array $tests, int $limit): string {
    $lines = array_map(fn (string $test): string => '- `'.$test.'`', array_slice($tests, 0, $limit));
    if (count($tests) > $limit) {
        $lines[] = '- … and '.(count($tests) - $limit).' more (see the regression-failures artifact)';
    }

    return implode("\n", $lines)."\n";
};

echo $files === null ? "## Full regression\n\n" : '## Focused tests ('.count($files)." files)\n\n";
foreach ($problems as $problem) {
    echo "**Failed:** {$problem}\n\n";
}
echo $total.' tests, '.count($failing).' failing; the known-failure baseline lists '.count($known).($files === null ? '' : ' in these files').".\n\n";
echo '### Regressions: failing and not in the baseline ('.count($new).")\n\n".($new === [] ? "None.\n" : $list($new, 200))."\n";
echo '### Known failures still failing ('.count($stillFailing).")\n\n".($stillFailing === [] ? "None.\n" : $list($stillFailing, 30))."\n";
if ($problems === []) {
    echo '### Known failures now passing: remove them from the baseline ('.count($nowPassing).")\n\n".($nowPassing === [] ? "None.\n" : $list($nowPassing, 50))."\n";
}

exit($problems === [] && $new === [] ? 0 : 1);
