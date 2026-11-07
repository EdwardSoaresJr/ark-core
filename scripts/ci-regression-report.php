<?php

/*
 * Compares a full-suite run with the last nightly run on main (.github/workflows/regression.yml).
 *
 *   php scripts/ci-regression-report.php --junit=<junit.xml> --out=<failures.txt> [--previous=<failures.txt>]
 *
 * Writes this run's failing tests (one "file::test" per line) to --out and a Markdown report to stdout:
 * new failures, failures already present in the previous run, and tests that now pass. Exits 1 when a test fails
 * that did not fail in the previous run (all failures count as new without one) or when the JUnit file is missing.
 */

$options = getopt('', ['junit:', 'out:', 'previous::']);
$junit = $options['junit'] ?? null;
$out = $options['out'] ?? null;
if (! is_string($junit) || ! is_string($out)) {
    fwrite(STDERR, "usage: php scripts/ci-regression-report.php --junit=<junit.xml> --out=<failures.txt> [--previous=<failures.txt>]\n");
    exit(2);
}

if (! is_file($junit) || ($xml = @simplexml_load_file($junit)) === false) {
    echo "## Full regression\n\nThe suite produced no readable JUnit report (`{$junit}`): it crashed or timed out. Treat as a failure.\n";
    exit(1);
}

$failing = [];
$total = 0;
foreach ($xml->xpath('//testcase') ?: [] as $case) {
    $total++;
    if (isset($case->failure) || isset($case->error)) {
        $file = (string) ($case['file'] ?? $case['class'] ?? '');
        $file = preg_replace('#^.*?/(tests/)#', '$1', $file) ?? $file;
        $failing[] = $file.'::'.(string) $case['name'];
    }
}
$failing = array_values(array_unique($failing));
sort($failing);
file_put_contents($out, $failing === [] ? '' : implode("\n", $failing)."\n");

$previousPath = $options['previous'] ?? null;
$hasPrevious = is_string($previousPath) && is_file($previousPath);
$previous = $hasPrevious ? array_values(array_filter(array_map('trim', file($previousPath) ?: []))) : [];

$new = $hasPrevious ? array_values(array_diff($failing, $previous)) : $failing;
$existing = $hasPrevious ? array_values(array_intersect($failing, $previous)) : [];
$fixed = $hasPrevious ? array_values(array_diff($previous, $failing)) : [];

$list = function (array $tests, int $limit = 200): string {
    $shown = array_slice($tests, 0, $limit);
    $lines = array_map(fn (string $test): string => '- `'.$test.'`', $shown);
    if (count($tests) > $limit) {
        $lines[] = '- … and '.(count($tests) - $limit).' more (see the failures artifact)';
    }

    return implode("\n", $lines)."\n";
};

echo "## Full regression\n\n";
echo $total.' tests, '.count($failing)." failing.\n\n";
echo $hasPrevious
    ? "Compared with the previous nightly run on `main`.\n\n"
    : "No previous nightly run to compare with: every failure is listed as new.\n\n";
echo '### New failures ('.count($new).")\n\n".($new === [] ? "None.\n" : $list($new))."\n";
if ($hasPrevious) {
    echo '### Already failing in the previous run ('.count($existing).")\n\n".($existing === [] ? "None.\n" : $list($existing, 50))."\n";
    echo '### Now passing ('.count($fixed).")\n\n".($fixed === [] ? "None.\n" : $list($fixed, 50))."\n";
}

exit($new === [] ? 0 : 1);
