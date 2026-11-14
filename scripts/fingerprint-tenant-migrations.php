#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * List and fingerprint Core tenant migration basenames for a release contract.
 * usage: php scripts/fingerprint-tenant-migrations.php [--root /path/to/ark-core]
 */

$root = dirname(__DIR__);
$args = array_slice($argv, 1);
for ($i = 0; $i < count($args); $i++) {
    if ($args[$i] === '--root') {
        $root = $args[++$i] ?? $root;
    }
}

$directories = [
    $root.'/database/migrations',
    $root.'/database/migrations/deferred',
];
$names = [];
foreach ($directories as $directory) {
    if (! is_dir($directory)) {
        continue;
    }
    foreach (scandir($directory) ?: [] as $file) {
        if (preg_match('/\A([0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6}_[A-Za-z0-9_]+)\.php\z/', $file, $match) === 1) {
            $names[] = $match[1];
        }
    }
}
$names = array_values(array_unique($names));
sort($names);

echo json_encode([
    'count' => count($names),
    'fingerprint' => 'sha256:'.hash('sha256', implode("\n", $names)),
    'migrations' => $names,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n";
