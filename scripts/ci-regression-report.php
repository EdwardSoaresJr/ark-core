<?php

/*
 * Checks a full-suite run against the committed known-failure baseline (.github/regression-known-failures.txt).
 *
 *   php scripts/ci-regression-report.php --junit=<junit.xml> --known=<baseline.txt> --out=<failures.txt> [--exit-code=<n>]
 *
 * Writes this run's failing tests (one "tests/...Test.php::test name" per line) to --out and a Markdown report to
 * stdout. A failure is a regression until it is fixed or explicitly added to the baseline; the previous run is never
 * an implicit baseline. Exits 1 when:
 *   - a test fails that the baseline does not list;
 *   - the run crashed or was stopped (--exit-code other than 0 or 1, or not a number) or left no readable JUnit report;
 *   - the run is incomplete: fewer tests than 90% of the baseline's "# tests:" count;
 *   - the baseline file is missing.
 * Baseline entries that now pass are reported for removal and do not fail the run.
 */

$options = getopt('', ['junit:', 'known:', 'out:', 'exit-code::']);
foreach (['junit', 'known', 'out'] as $required) {
    if (! is_string($options[$required] ?? null)) {
        fwrite(STDERR, "usage: php scripts/ci-regression-report.php --junit=<junit.xml> --known=<baseline.txt> --out=<failures.txt> [--exit-code=<n>]\n");
        exit(2);
    }
}

$problems = [];
$exitCode = isset($options['exit-code']) ? trim((string) $options['exit-code']) : null;
if ($exitCode !== null && ! in_array($exitCode, ['0', '1'], true)) {
    $problems[] = ctype_digit($exitCode)
        ? "The suite exited with code {$exitCode}: it crashed or was stopped before finishing."
        : 'The suite recorded no exit code: it was stopped or timed out before finishing.';
}

$failing = [];
$total = 0;
$xml = is_file($options['junit']) ? @simplexml_load_file($options['junit']) : false;
if ($xml === false) {
    $problems[] = "The suite left no readable JUnit report ({$options['junit']}).";
} else {
    foreach ($xml->xpath('//testcase') ?: [] as $case) {
        $total++;
        if (isset($case->failure) || isset($case->error)) {
            $file = (string) ($case['file'] ?? '');
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
if ($xml !== false && $total > 0 && $expectedTests !== null && $total < (int) floor($expectedTests * 0.9)) {
    $problems[] = "Only {$total} tests ran; the baseline expects about {$expectedTests}. The run is incomplete.";
}

$new = array_values(array_diff($failing, $known));
$stillFailing = array_values(array_intersect($failing, $known));
$nowPassing = $xml !== false && $total > 0 ? array_values(array_diff($known, $failing)) : [];

$list = function (array $tests, int $limit): string {
    $lines = array_map(fn (string $test): string => '- `'.$test.'`', array_slice($tests, 0, $limit));
    if (count($tests) > $limit) {
        $lines[] = '- … and '.(count($tests) - $limit).' more (see the regression-failures artifact)';
    }

    return implode("\n", $lines)."\n";
};

echo "## Full regression\n\n";
foreach ($problems as $problem) {
    echo "**Failed:** {$problem}\n\n";
}
echo $total.' tests, '.count($failing).' failing; the known-failure baseline lists '.count($known).".\n\n";
echo '### Regressions: failing and not in the baseline ('.count($new).")\n\n".($new === [] ? "None.\n" : $list($new, 200))."\n";
echo '### Known failures still failing ('.count($stillFailing).")\n\n".($stillFailing === [] ? "None.\n" : $list($stillFailing, 30))."\n";
echo '### Known failures now passing: remove them from the baseline ('.count($nowPassing).")\n\n".($nowPassing === [] ? "None.\n" : $list($nowPassing, 50))."\n";

exit($problems === [] && $new === [] ? 0 : 1);
