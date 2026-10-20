<?php

declare(strict_types=1);

/**
 * Validates a source-release record and the check runs named for that source commit.
 * The tag and the Git fetch stay in the shell. This does not deploy.
 */

$command = $argv[1] ?? '';

if ($command === 'record') {
    $path = $argv[2] ?? '';
    $record = json_decode((string) file_get_contents($path), true);
    if (! is_array($record)) {
        refuse('The release record is not JSON.');
    }
    if (($record['schema'] ?? null) !== 'ark-core-source-release/v1') {
        refuse('The release record schema is not ark-core-source-release/v1.');
    }
    $source = requiredSha($record, 'source_sha');
    $baseline = requiredSha($record, 'baseline_sha');
    $diff = requiredHex($record, 'diff_sha256', 64);
    $repository = $record['repository'] ?? '';
    if (! is_string($repository) || preg_match('/\A[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+\z/', $repository) !== 1) {
        refuse('The release record repository is not an owner/name.');
    }
    $approval = $record['approval'] ?? null;
    if (! is_array($approval) || ($approval['state'] ?? null) !== 'source_approved') {
        refuse('The release record does not approve the source commit.');
    }
    $pull = $approval['pull_request'] ?? null;
    if (! is_int($pull) || $pull < 1) {
        refuse('The release record has no pull request approval.');
    }
    $stages = $record['stages'] ?? null;
    if (! is_array($stages)) {
        refuse('The release record has no stages.');
    }
    if (($stages['source_approved'] ?? null) !== true || ($stages['tests_verified'] ?? null) !== true) {
        refuse('The release record has not approved the source and its tests.');
    }
    if (($stages['production_deployment_authorized'] ?? null) !== false || ($stages['deployment_completed'] ?? null) !== false) {
        refuse('This record authorizes production deployment. Source publication requires a record whose deployment stages are not authorized.');
    }
    emit([
        'source_sha' => $source,
        'baseline_sha' => $baseline,
        'diff_sha256' => $diff,
        'repository' => $repository,
        'pull_request' => (string) $pull,
    ]);
}

if ($command === 'checks') {
    $expected = $argv[2] ?? '';
    if (preg_match('/\A[a-f0-9]{40}\z/', $expected) !== 1) {
        refuse('The source SHA for check verification is not a full SHA.');
    }
    $payload = json_decode(stream_get_contents(STDIN) ?: '', true);
    if (! is_array($payload)) {
        refuse('Check runs were not returned.');
    }
    $total = $payload['total_count'] ?? null;
    if (! is_int($total)) {
        refuse('Check runs did not include a total.');
    }
    if ($total > 100) {
        refuse('Too many check runs to verify the latest result.');
    }
    $runs = $payload['check_runs'] ?? null;
    if (! is_array($runs)) {
        refuse('Check runs were not returned.');
    }
    foreach (['gitleaks', 'pest'] as $name) {
        $latest = null;
        foreach ($runs as $run) {
            if (! is_array($run) || ($run['name'] ?? null) !== $name) {
                continue;
            }
            if ($latest === null || strcmp((string) ($run['completed_at'] ?? ''), (string) ($latest['completed_at'] ?? '')) > 0) {
                $latest = $run;
            }
        }
        if ($latest === null) {
            refuse("Missing {$name} check for {$expected}.");
        }
        $head = (string) ($latest['head_sha'] ?? '');
        if ($head !== $expected) {
            refuse("Stale {$name} check is for {$head}, not {$expected}.");
        }
        $app = $latest['app']['slug'] ?? '';
        if ($app !== 'github-actions') {
            refuse("{$name} check was not recorded by GitHub Actions.");
        }
        $status = (string) ($latest['status'] ?? '');
        $conclusion = (string) ($latest['conclusion'] ?? '');
        if ($status !== 'completed' || $conclusion !== 'success') {
            refuse("{$name} check is {$status}/{$conclusion}. Missing, skipped, stale, or failed checks block publication.");
        }
    }
    fwrite(STDOUT, "checks ok\n");
    exit(0);
}

if ($command === 'approval') {
    $payload = json_decode(stream_get_contents(STDIN) ?: '', true);
    if (! is_array($payload)) {
        refuse('Pull request approval was not returned.');
    }
    $pull = $payload['pull'] ?? null;
    $reviews = $payload['reviews'] ?? null;
    $tipBlob = $payload['tip_blob'] ?? '';
    $reviewBlobs = $payload['review_blobs'] ?? null;
    if (! is_array($pull) || ! is_array($reviews) || ! is_array($reviewBlobs) || ! is_string($tipBlob) || $tipBlob === '') {
        refuse('Pull request approval was not returned.');
    }
    if (($pull['merged'] ?? null) !== true || ($pull['base']['ref'] ?? null) !== 'release-authorization') {
        refuse('The release record pull request is not merged into release-authorization.');
    }
    $author = (string) ($pull['user']['login'] ?? '');
    $approved = false;
    foreach ($reviews as $review) {
        if (! is_array($review) || ($review['state'] ?? null) !== 'APPROVED') {
            continue;
        }
        $reviewer = (string) ($review['user']['login'] ?? '');
        $commit = (string) ($review['commit_id'] ?? '');
        if ($reviewer === '' || $reviewer === $author || $commit === '') {
            continue;
        }
        if (($reviewBlobs[$commit] ?? null) !== $tipBlob) {
            continue;
        }
        $approved = true;
    }
    if (! $approved) {
        refuse('The release record has no approval of its current contents by someone other than its author.');
    }
    fwrite(STDOUT, "approval ok\n");
    exit(0);
}

fwrite(STDERR, "Usage: core-release-authorize.php record|checks|approval\n");
exit(2);

function requiredSha(array $record, string $key): string
{
    $value = $record[$key] ?? '';
    if (! is_string($value) || preg_match('/\A[a-f0-9]{40}\z/', $value) !== 1) {
        refuse("The release record {$key} is not a full SHA.");
    }

    return $value;
}

function requiredHex(array $record, string $key, int $length): string
{
    $value = $record[$key] ?? '';
    if (! is_string($value) || preg_match('/\A[a-f0-9]{'.$length.'}\z/', $value) !== 1) {
        refuse("The release record {$key} is not a {$length}-character hash.");
    }

    return $value;
}

function emit(array $fields): never
{
    foreach ($fields as $key => $value) {
        if (! preg_match('/\A[a-z0-9_]+\z/', (string) $key) || ! preg_match('/\A[A-Za-z0-9_\/.-]+\z/', $value)) {
            refuse('The release record produced an unusable field.');
        }
        fwrite(STDOUT, $key.'='.$value."\n");
    }
    exit(0);
}

function refuse(string $message): never
{
    fwrite(STDERR, 'REFUSING: '.$message."\n");
    exit(1);
}
