<?php

declare(strict_types=1);

/**
 * Fails when public Core contains functional communications code. Policy: docs/PRODUCT_BOUNDARY.md.
 *
 *   php scripts/assert-no-communications.php --routes routes.json [--tables tables.json] [--previous-baseline base.json]
 *   php scripts/assert-no-communications.php --routes routes.json --write-baseline   (only to shrink the baseline)
 *
 * routes.json is `php artisan route:list --json`. tables.json, when given, is the JSON list of tables a fresh
 * migration created. base.json is the baseline on the branch being merged into; an entry it does not have fails,
 * so the baseline can only shrink. Every violation not in the baseline fails, every baseline entry that no longer
 * occurs fails, and a missing baseline or route list fails. The baseline reaches zero when the extraction is done.
 */
$options = getopt('', ['root:', 'baseline:', 'routes:', 'tables:', 'previous-baseline:', 'write-baseline']);
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
const ROUTE_URI = '#(^|/)(webhooks/communications|[^/]*twilio[^/]*|conversations?|communications|comms|inbox|calls?|telephony|voice|voicemail|sms|mms|recordings?|incoming-calls?|incoming-call|caller-lookup|softphone|sip[^/]*|provision|outbound-mms|webhooks/cloud/fabric)(/|$)#i';
const ROUTE_NAME = '/(^|\.)(webhooks\.communications|twilio|conversations?|communications|comms|inbox|calls?|telephony|voice|voicemail|sms|incoming-calls?|incoming-call|caller-lookup|softphone)(\.|$|-)/i';
const CHANNEL = '/(incoming-calls?|conversations?|comms|communications|calls?|voice|telephony)/i';
const PERMISSION = "/'((?:comms|communications|telephony|voice|messaging|conversations|calls)\\.[a-z0-9_.-]+)'/";
const TEXT = ['api.twilio.com', 'sip.twilio.com', 'X-Twilio-Signature', '<Dial', '<Say', '<Record', '<Sip', '<Conference', 'api.postmarkapp.com', 'api.sendgrid.com', 'api.mailgun.net', 'twilioConfigured(', "env('TWILIO_", "env('VOICE_SIP_", "env('PJSIP_", '.auth.password', 'twilio-webhooks.php'];
// Artisan commands that send, receive, or route communications on the shop schedule.
const SCHEDULED = '/^(comms|communications|telephony|voice|sms|messaging|conversations|calls?)[:.-]|^appointments:send-reminders$|^ark:platform-heartbeat$/';
const PACKAGES = '#^(twilio/|@twilio/|vonage/|@vonage/|plivo/|telnyx/|sendgrid/|@sendgrid/|mailgun/|wildbit/|postmark|symfony/[a-z-]+-mailer$|aws/aws-sdk-php$)#';
const CONFIGS = ['communications.php', 'telephony.php', 'voice.php', 'voice-transport.php', 'legacy-arksms-import.php', 'phone_verification.php', 'email_verification.php'];
const VIEW_PATH = '#(^|[/_.-])(communications?|comms|conversations?|messaging|messenger|telephony|voice|voicemail|sms|mms|softphone|inbox|caller-lookup|calls?|incoming-calls?|customer-email)([/_.-]|$)#i';
const TERMS = '/(?<![a-z])(twilio|twiml|postmark|sendgrid|mailgun|vonage|plivo|telnyx|voicemail)|(?<![a-z])sip:[a-z0-9+]/i';
const COLUMNS = "/'(telephony_[a-z_]+|voicemail_[a-z_]+|recording_disclaimer|communications_[a-z_]+|sms_[a-z_]+|twilio_[a-z_]+|postmark_[a-z_]+|call_session_[a-z_]+)'/";
const CLASS_NAME = '/(Comms|Communications?|Conversations?|Telephony|Voice|Sms|Mms|Voicemail|Inbox|Softphone|IncomingCall|CallSession|Call(s|Queue|Recording|Handle|Show|Context|Library|Log|Row|Flow))[A-Za-z]*\.php$/';
const IDENTIFIERS = '/\\b(\\w*Telephony[A-Z]\\w*|telephony_[a-z_]+|\\w*IncomingCall\\w*|incoming[-_]calls?|incomingCall\\w*|\\w*ConversationMessage\\w*|conversation_message\\w*|\\w*Voicemail\\w*|ring_group\\w*|arkTelephonyCallback|initiateTelephonyCallback|arkInitiateTelephonyCallback|ark-telephony[\\w-]*|comms-interrupts|AppointmentSms\\w*|unassigned_conversation\\w*|call_sessions|Conversation[A-Z]\\w*|conversation-[a-z][a-z-]*|conversation\\.[a-z_]+|appointment-sms)\\b|operations\\.(conversations|incoming-calls)\\b|app\\/communications|reminder SMS|(?:delivery|channel)[^\\n]{0,14}\\x27sms\\x27|@case\\(\\x27sms\\x27\\)|in:sms|\\x27(inbox|comms|Telephony)\\x27/';
const LINK = '/\b(conversation_id|call_session_id|conversationId|callSessionId|conversation_message_id)\b|\'conversation\'\s*=>/';
const TRAINING = '/\b(sms|mms|texting|inbox|voicemail|incoming calls?|telephony|ring group|conversations?)\b/i';
// Framework files that may mention a provider without being a communications product. Each needs a reason.
const ALLOWED = [
    'config/mail.php' => 'Laravel mailer configuration, used for staff sign-in and password-reset mail',
];

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
        if (str_contains($file->getPathname(), '/bootstrap/cache/')) {
            continue;
        }
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
foreach (['app', 'routes', 'config', 'resources', 'bootstrap'] as $dir) {
    foreach ($files($dir, ['.php', '.js', '.ts', '.vue']) as $path => $contents) {
        foreach (TEXT as $needle) {
            if (str_contains($contents, $needle)) {
                $add('text', $path.' contains '.$needle);
            }
        }
    }
}
foreach ($files('resources', ['.blade.php']) as $path => $contents) {
    if ($references($contents)) {
        $add('reference', $path);
    }
}
foreach ($files('app', ['.php']) as $path => $contents) {
    if ($inCodeDir($path)) {
        continue;
    }
    if (preg_match(CLASS_NAME, basename($path))) {
        $add('name', $path);
    }
    if (preg_match(COLUMNS, $contents)) {
        $add('setting', $path);
    }
}
foreach (['app', 'resources', 'database/migrations'] as $dir) {
    foreach ($files($dir, ['.php', '.js']) as $path => $contents) {
        if (! $inCodeDir($path) && preg_match(LINK, $contents)) {
            $add('link', $path);
        }
    }
}
foreach (['resources/views/operations/learn', 'resources/views/install', 'app/Ark/Operations/Learn/Catalog'] as $dir) {
    foreach ($files($dir, ['.blade.php', '.php']) as $path => $contents) {
        if (preg_match(TRAINING, str_ends_with($path, '.blade.php') ? strip_tags($contents) : $contents)) {
            $add('doc', $path);
        }
    }
}
foreach (['app', 'routes', 'config', 'database', 'bootstrap', 'resources', 'tests'] as $dir) {
    foreach ($files($dir, ['.php', '.js', '.ts', '.vue', '.css']) as $path => $contents) {
        if ($inCodeDir($path) || isset(ALLOWED[$path]) || $path === 'tests/Feature/Architecture/CommunicationsBoundaryTest.php') {
            continue;
        }
        if (preg_match(TERMS, $contents) || ($dir !== 'tests' && preg_match(IDENTIFIERS, $contents))) {
            $add($dir === 'tests' ? 'test' : 'term', $path);
        }
    }
}
foreach ($files('database/migrations', ['.php']) as $path => $contents) {
    preg_match_all("/Schema::(?:table|create)\\(\\s*'([a-z0-9_]+)'/", $contents, $altered);
    $touchesTable = array_filter($altered[1], fn (string $table): bool => (bool) preg_match(TABLES, $table));
    if ($touchesTable !== [] || preg_match(COLUMNS, $contents)) {
        $add('migration', $path);
    }
}
foreach ($files('resources', ['.blade.php', '.js', '.ts', '.vue', '.css']) as $path => $contents) {
    $relative = substr($path, strlen('resources/'));
    if (preg_match(VIEW_PATH, $relative) || str_contains($contents, "route('operations.communications.")) {
        $add('screen', $path);
    }
}
foreach ($files('app', ['.php']) as $path => $contents) {
    if (preg_match('/implements\s+[^{]*\bShouldQueue\b/', $contents) && ($inCodeDir($path) || $references($contents))) {
        $add('job', $path);
    }
    if (str_contains($contents, 'Illuminate\\Console\\Scheduling\\Schedule')) {
        preg_match_all("/'([a-z0-9-]+:[a-z0-9:-]+)'/", $contents, $matches);
        foreach (array_unique($matches[1]) as $command) {
            if (preg_match(SCHEDULED, $command)) {
                $add('schedule', $command.' in '.$path);
            }
        }
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
        $class = explode('@', (string) ($route['action'] ?? ''))[0];
        $handler = str_starts_with($class, 'App\\') ? 'app/'.str_replace('\\', '/', substr($class, 4)).'.php' : '';
        if ($handler !== '' && is_file($root.'/'.$handler) && ! $inCodeDir($handler)) {
            $add('handler', $handler);
        }
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
        'note' => 'Communications code still being moved to ARK Shop. Entries may only be removed. The extraction ends with this list empty.',
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

$grown = [];
if (isset($options['previous-baseline'])) {
    $previous = json_decode((string) @file_get_contents((string) $options['previous-baseline']), true)['violations'] ?? null;
    if (! is_array($previous)) {
        fwrite(STDERR, "The previous baseline is missing or not valid JSON.\n");
        exit(2);
    }
    $grown = array_values(array_diff($baseline, $previous));
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
if ($grown !== []) {
    fwrite(STDERR, "The baseline gained entries the target branch does not have. It may only shrink:\n- ".implode("\n- ", $grown)."\n");
}
exit($new === [] && $stale === [] && $grown === [] ? 0 : 1);
