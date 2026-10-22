<?php

declare(strict_types=1);

/**
 * Fails when public Core contains functional communications code. Policy: docs/PRODUCT_BOUNDARY.md.
 *
 *   php scripts/assert-no-communications.php --routes routes.json [--tables tables.json]
 *   php scripts/assert-no-communications.php --routes routes.json --write-baseline   (only to shrink the baseline)
 *
 * routes.json is `php artisan route:list --json`. tables.json, when given, is the JSON list of tables a fresh
 * migration created. Every violation not in the baseline fails, every baseline entry that no longer occurs fails,
 * and a missing baseline or route list fails.
 */
$options = getopt('', ['root:', 'baseline:', 'routes:', 'tables:', 'write-baseline']);
$root = rtrim((string) ($options['root'] ?? dirname(__DIR__)), '/');
$baselinePath = (string) ($options['baseline'] ?? $root.'/scripts/communications-baseline.json');
$routesPath = (string) ($options['routes'] ?? '');

if (! is_dir($root.'/app')) {
    fwrite(STDERR, "No Core tree at {$root}.\n");
    exit(2);
}
if ($routesPath === '' || ! is_file($routesPath)) {
    fwrite(STDERR, "A route list is required: --routes <php artisan route:list --json output>.\n");
    exit(2);
}
$routes = json_decode((string) file_get_contents($routesPath), true);
if (! is_array($routes) || $routes === []) {
    fwrite(STDERR, "The route list is empty or not JSON.\n");
    exit(2);
}

const CODE_DIRS = [
    'app/Ark/Operations/Communications',
    'app/Ark/Operations/Conversations',
    'app/Ark/Operations/Messaging',
    'app/Ark/Operations/Telephony',
    'app/Ark/Operations/EmailVerification',
    'app/Ark/Operations/PhoneVerification',
    'app/Ark/Communications',
    'app/Ark/Voice',
    'app/Ark/Mail',
    'app/Ark/Platform/Communications',
    'app/Ark/Platform/Voice',
    'app/Ark/Platform/Mail',
];
const TABLES = '/^(conversations|conversation_[a-z_]+|call_sessions|session_events|telephony_[a-z_]+|communication_[a-z_]+|operational_communications|scheduled_outbound_messages|internal_messages|phone_sms_capabilities|staff_coaching_logs|email_verifications|phone_verifications)$/';
const ROUTE_URI = '#(^|/)(webhooks/communications|[^/]*twilio[^/]*|conversations?|communications|comms|inbox|calls?|telephony|voice|voicemail|sms|mms|recordings?|incoming-calls?|incoming-call|caller-lookup|softphone|sip[^/]*)(/|$)#i';
const ROUTE_NAME = '/(^|\.)(webhooks\.communications|twilio|conversations?|communications|comms|inbox|calls?|telephony|voice|voicemail|sms|incoming-calls?|incoming-call|caller-lookup|softphone)(\.|$|-)/i';
const CHANNEL = '/(incoming-calls?|conversations?|comms|communications|calls?|voice|telephony)/i';
const PERMISSION = "/'((?:comms|communications|telephony|voice|messaging|conversations|calls)\\.[a-z0-9_.-]+)'/";
const TEXT = ['api.twilio.com', 'sip.twilio.com', 'X-Twilio-Signature', '<Dial', '<Say', '<Record', '<Sip', '<Conference', 'api.postmarkapp.com', 'api.sendgrid.com', 'api.mailgun.net'];
const PACKAGES = '#^(twilio/|@twilio/|vonage/|@vonage/|plivo/|telnyx/|sendgrid/|@sendgrid/|mailgun/|wildbit/|postmark|symfony/[a-z-]+-mailer$|aws/aws-sdk-php$)#';
const CONFIGS = ['communications.php', 'telephony.php', 'voice.php', 'voice-transport.php', 'legacy-arksms-import.php'];
const VIEW_PATH = '#(^|/)(communications|conversations|telephony|voice|sms|softphone|inbox|caller-lookup|calls?|incoming-calls?)(/|[-.]|$)#i';

$namespaces = array_map(
    fn (string $dir): string => 'App\\'.str_replace('/', '\\', substr($dir, 4)).'\\',
    CODE_DIRS,
);

$violations = [];
$add = function (string $kind, string $subject) use (&$violations): void {
    $violations[$kind.': '.$subject] = true;
};
$files = function (string $dir, array $extensions) use ($root): iterable {
    if (! is_dir($root.'/'.$dir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $name = $file->getFilename();
        foreach ($extensions as $extension) {
            if (str_ends_with($name, $extension)) {
                yield substr($file->getPathname(), strlen($root) + 1) => (string) file_get_contents($file->getPathname());
                break;
            }
        }
    }
};
$inCodeDir = function (string $path): bool {
    foreach (CODE_DIRS as $dir) {
        if (str_starts_with($path, $dir.'/')) {
            return true;
        }
    }

    return false;
};
$references = function (string $contents) use ($namespaces): bool {
    foreach ($namespaces as $namespace) {
        if (str_contains($contents, $namespace)) {
            return true;
        }
    }

    return false;
};

foreach (['app', 'routes', 'database', 'bootstrap', 'config'] as $dir) {
    foreach ($files($dir, ['.php']) as $path => $contents) {
        if ($inCodeDir($path)) {
            $add('code', $path);
        } elseif ($references($contents)) {
            $add('reference', $path);
        }
        if (preg_match('/Mail::(to|send|raw|queue|later)\(|extends\s+Mailable\b|new\s+MailMessage\b|function\s+toMail\(/', $contents)) {
            $add('mail', $path);
        }
    }
}
foreach ($files('tests', ['.php']) as $path => $contents) {
    if ($path === 'tests/Feature/Architecture/CommunicationsBoundaryTest.php') {
        continue;
    }
    if ($references($contents) || preg_match('#webhooks/communications|webhooks\.communications\.|operations\.communications\.#', $contents)) {
        $add('test', $path);
    }
}
foreach (['app', 'routes', 'config', 'resources'] as $dir) {
    foreach ($files($dir, ['.php', '.js', '.ts', '.vue']) as $path => $contents) {
        foreach (TEXT as $needle) {
            if (str_contains($contents, $needle)) {
                $add('text', $path.' contains '.$needle);
            }
        }
    }
}
foreach ($files('resources', ['.blade.php', '.js', '.ts', '.vue', '.css']) as $path => $contents) {
    $relative = substr($path, strlen('resources/'));
    if (preg_match(VIEW_PATH, $relative) || str_contains($contents, "route('operations.communications.")) {
        $add('screen', $path);
    }
}
foreach (CONFIGS as $config) {
    if (is_file($root.'/config/'.$config)) {
        $add('config', 'config/'.$config);
    }
}
foreach ($files('database/migrations', ['.php']) as $path => $contents) {
    preg_match_all("/Schema::create\\(\\s*'([a-z0-9_]+)'/", $contents, $matches);
    foreach ($matches[1] as $table) {
        if (preg_match(TABLES, $table)) {
            $add('table', $table);
        }
    }
}
if (isset($options['tables'])) {
    $tables = json_decode((string) @file_get_contents((string) $options['tables']), true);
    if (! is_array($tables) || $tables === []) {
        fwrite(STDERR, "The table list is empty or not JSON.\n");
        exit(2);
    }
    foreach ($tables as $table) {
        $table = substr(strrchr('.'.(string) $table, '.'), 1);
        if (preg_match(TABLES, $table)) {
            $add('table', $table);
        }
    }
}
foreach ($files('docs', ['.md']) as $path => $contents) {
    if (preg_match(VIEW_PATH, substr($path, strlen('docs/')))) {
        $add('doc', $path);
    }
}
foreach (['config/ark-permissions.php', 'app/Ark/Runtime/Authorization/ArkCapability.php'] as $file) {
    if (is_file($root.'/'.$file)) {
        preg_match_all(PERMISSION, (string) file_get_contents($root.'/'.$file), $matches);
        foreach (array_unique($matches[1]) as $permission) {
            $add('permission', $permission);
        }
    }
}
foreach ($files('database/seeders', ['.php']) as $path => $contents) {
    preg_match_all(PERMISSION, $contents, $matches);
    foreach (array_unique($matches[1]) as $permission) {
        $add('permission', $permission);
    }
}
if (is_file($root.'/routes/channels.php')) {
    preg_match_all("/Broadcast::channel\\(\\s*'([^']+)'/", (string) file_get_contents($root.'/routes/channels.php'), $matches);
    foreach ($matches[1] as $channel) {
        if (preg_match(CHANNEL, $channel)) {
            $add('channel', $channel);
        }
    }
}
foreach ($routes as $route) {
    $uri = (string) ($route['uri'] ?? '');
    $name = (string) ($route['name'] ?? '');
    if (preg_match(ROUTE_URI, $uri) || ($name !== '' && preg_match(ROUTE_NAME, $name))) {
        $add('route', str_replace('|HEAD', '', (string) ($route['method'] ?? '')).' '.$uri);
    }
}
foreach (['composer.json' => ['require', 'require-dev'], 'package.json' => ['dependencies', 'devDependencies']] as $manifest => $sections) {
    $data = is_file($root.'/'.$manifest) ? json_decode((string) file_get_contents($root.'/'.$manifest), true) : null;
    foreach ($sections as $section) {
        foreach (array_keys(is_array($data[$section] ?? null) ? $data[$section] : []) as $package) {
            if (preg_match(PACKAGES, (string) $package)) {
                $add('package', $manifest.' '.$package);
            }
        }
    }
}

$found = array_keys($violations);
sort($found);

if (isset($options['write-baseline'])) {
    $exists = is_file($baselinePath);
    $previous = $exists ? (json_decode((string) file_get_contents($baselinePath), true)['violations'] ?? []) : [];
    $added = array_values(array_diff($found, $previous));
    if ($exists && $added !== []) {
        fwrite(STDERR, "Refusing to grow the baseline. New violations:\n- ".implode("\n- ", $added)."\n");
        exit(1);
    }
    file_put_contents($baselinePath, json_encode([
        'policy' => 'docs/PRODUCT_BOUNDARY.md',
        'note' => 'Communications code still being moved to ARK Shop. Entries may only be removed.',
        'violations' => $found,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    fwrite(STDOUT, 'Baseline written: '.count($found)." entries.\n");
    exit(0);
}

if (! is_file($baselinePath)) {
    fwrite(STDERR, "The communications baseline is missing: {$baselinePath}.\n");
    exit(2);
}
$baseline = json_decode((string) file_get_contents($baselinePath), true)['violations'] ?? null;
if (! is_array($baseline)) {
    fwrite(STDERR, "The communications baseline is not valid JSON.\n");
    exit(2);
}

$new = array_values(array_diff($found, $baseline));
$stale = array_values(array_diff($baseline, $found));
$byKind = [];
foreach ($found as $entry) {
    $kind = strstr($entry, ':', true);
    $byKind[$kind] = ($byKind[$kind] ?? 0) + 1;
}
ksort($byKind);
$summary = implode(', ', array_map(fn ($kind, $count) => "{$kind} {$count}", array_keys($byKind), $byKind));
fwrite(STDOUT, 'Communications still in Core: '.count($found).($summary === '' ? '' : " ({$summary})").".\n");

if ($new !== []) {
    fwrite(STDERR, "New communications code in Core. It belongs in ARK Shop (docs/PRODUCT_BOUNDARY.md):\n- ".implode("\n- ", $new)."\n");
}
if ($stale !== []) {
    fwrite(STDERR, "These baseline entries no longer occur. Remove them with --write-baseline:\n- ".implode("\n- ", $stale)."\n");
}
exit($new === [] && $stale === [] ? 0 : 1);
