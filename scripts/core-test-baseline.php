<?php

declare(strict_types=1);

/**
 * Known test failures for one test environment.
 *
 * A baseline is a TSV of `<test id>\t<tracking note>`. The id is Pest's JUnit `file` attribute,
 * `tests/<path>.php::<test name>`. Every entry needs its own note. Lines starting with `#` are comments.
 *
 *   failures <junit.xml>               print the failing test ids
 *   compare  <junit.xml> <baseline>    exit 1 when a test fails that the baseline does not list
 *   growth   <old baseline> <new>      print ids the new baseline adds, one per line
 */

$command = $argv[1] ?? '';

if ($command === 'failures') {
    foreach (failures($argv[2] ?? '') as $id) {
        fwrite(STDOUT, $id."\n");
    }
    exit(0);
}

if ($command === 'compare') {
    $failing = failures($argv[2] ?? '');
    $known = baseline($argv[3] ?? '');
    $new = array_values(array_diff($failing, array_keys($known)));
    $fixed = array_values(array_diff(array_keys($known), $failing));
    sort($new);
    sort($fixed);
    foreach ($fixed as $id) {
        fwrite(STDOUT, "passes now, remove from the baseline: {$id}\n");
    }
    if ($new !== []) {
        foreach ($new as $id) {
            fwrite(STDERR, "new failure: {$id}\n");
        }
        refuse(count($new).' test(s) failed that the baseline does not list.');
    }
    fwrite(STDOUT, 'baseline ok: '.count($failing).' known failure(s), '.count($fixed)." fixed\n");
    exit(0);
}

if ($command === 'growth') {
    $old = baseline($argv[2] ?? '', allowMissing: true);
    $new = baseline($argv[3] ?? '');
    $added = array_values(array_diff(array_keys($new), array_keys($old)));
    sort($added);
    foreach ($added as $id) {
        fwrite(STDOUT, $id."\n");
    }
    exit(0);
}

fwrite(STDERR, "Usage: core-test-baseline.php failures|compare|growth\n");
exit(2);

/**
 * @return list<string>
 */
function failures(string $path): array
{
    if (! is_file($path)) {
        refuse("JUnit report {$path} is missing.");
    }
    $xml = @simplexml_load_file($path);
    if ($xml === false) {
        refuse("JUnit report {$path} is not XML.");
    }
    $ids = [];
    $cases = 0;
    foreach ($xml->xpath('//testcase') ?: [] as $case) {
        $cases++;
        if (! isset($case->failure) && ! isset($case->error)) {
            continue;
        }
        $id = (string) ($case['file'] ?? '');
        if ($id === '' || ! str_starts_with($id, 'tests/')) {
            refuse('A failing test case has no tests/ file id.');
        }
        $ids[$id] = true;
    }
    if ($cases === 0) {
        refuse("JUnit report {$path} has no test cases.");
    }

    return array_keys($ids);
}

/**
 * @return array<string, string>
 */
function baseline(string $path, bool $allowMissing = false): array
{
    if (! is_file($path)) {
        if ($allowMissing) {
            return [];
        }
        refuse("Baseline {$path} is missing.");
    }
    $entries = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
        if (trim($line) === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode("\t", $line, 2);
        $id = trim($parts[0]);
        $note = trim($parts[1] ?? '');
        if (! str_starts_with($id, 'tests/') || ! str_contains($id, '.php::')) {
            refuse('Baseline line '.($number + 1).' is not a tests/<path>.php::<name> id.');
        }
        if ($note === '') {
            refuse('Baseline line '.($number + 1).' has no tracking note.');
        }
        if (isset($entries[$id])) {
            refuse('Baseline line '.($number + 1).' repeats an id.');
        }
        $entries[$id] = $note;
    }

    return $entries;
}

function refuse(string $message): never
{
    fwrite(STDERR, 'REFUSING: '.$message."\n");
    exit(1);
}
