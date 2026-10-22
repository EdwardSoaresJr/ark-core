<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

function communicationsCheck(array $arguments): array
{
    $command = array_merge([PHP_BINARY, base_path('scripts/assert-no-communications.php')], $arguments);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $out];
}

function communicationsFixture(): string
{
    $root = sys_get_temp_dir().'/ark-comms-boundary-'.bin2hex(random_bytes(4));
    foreach (['app/Ark/Operations/RepairOrders', 'routes', 'database/migrations', 'config', 'resources/views', 'tests', 'docs'] as $dir) {
        mkdir($root.'/'.$dir, 0777, true);
    }
    file_put_contents($root.'/app/Ark/Operations/RepairOrders/RepairOrder.php', "<?php\nnamespace App\\Ark\\Operations\\RepairOrders;\nclass RepairOrder {}\n");
    file_put_contents($root.'/routes.json', json_encode([['method' => 'GET|HEAD', 'uri' => 'app/repair-orders', 'name' => 'operations.repair-orders.index']]));
    file_put_contents($root.'/composer.json', json_encode(['require' => ['laravel/framework' => '^13.0']]));

    return $root;
}

test('Core holds no communications code beyond the shrinking baseline, measured on a fresh install', function (): void {
    Artisan::call('route:list', ['--json' => true]);
    $routes = tempnam(sys_get_temp_dir(), 'routes');
    file_put_contents($routes, Artisan::output());
    $tables = tempnam(sys_get_temp_dir(), 'tables');
    file_put_contents($tables, json_encode(Schema::getTableListing(schemaQualified: false)));

    [$exit, $out] = communicationsCheck(['--routes', $routes, '--tables', $tables]);

    expect($exit)->toBe(0, $out);
});

test('a clean tree with an empty baseline passes', function (): void {
    $root = communicationsFixture();
    file_put_contents($root.'/baseline.json', json_encode(['violations' => []]));

    [$exit, $out] = communicationsCheck(['--root', $root, '--baseline', $root.'/baseline.json', '--routes', $root.'/routes.json']);

    expect($exit)->toBe(0, $out);
});

test('new communications code fails', function (string $path, string $contents, string $expected): void {
    $root = communicationsFixture();
    file_put_contents($root.'/baseline.json', json_encode(['violations' => []]));
    @mkdir(dirname($root.'/'.$path), 0777, true);
    file_put_contents($root.'/'.$path, $contents);

    [$exit, $out] = communicationsCheck(['--root', $root, '--baseline', $root.'/baseline.json', '--routes', $root.'/routes.json']);

    expect($exit)->toBe(1)->and($out)->toContain($expected);
})->with([
    'a telephony class' => ['app/Ark/Operations/Telephony/Dialer.php', "<?php\n", 'code: app/Ark/Operations/Telephony/Dialer.php'],
    'a reference from shop code' => ['app/Ark/Operations/RepairOrders/Notify.php', "<?php\nuse App\\Ark\\Operations\\Messaging\\Sender;\n", 'reference: app/Ark/Operations/RepairOrders/Notify.php'],
    'a provider host' => ['app/Ark/Operations/RepairOrders/Texts.php', "<?php\n\$u = 'https://api.twilio.com/2010-04-01';\n", 'contains api.twilio.com'],
    'TwiML' => ['app/Ark/Operations/RepairOrders/Answer.php', "<?php\n\$x = '<Response><Dial>1</Dial></Response>';\n", 'contains <Dial'],
    'a communications table' => ['database/migrations/2030_01_01_000000_x.php', "<?php\nSchema::create('conversation_messages', fn () => null);\n", 'table: conversation_messages'],
    'customer mail' => ['app/Ark/Operations/RepairOrders/Mailer.php', "<?php\nMail::to(\$c)->send(\$m);\n", 'mail: app/Ark/Operations/RepairOrders/Mailer.php'],
    'a config file' => ['config/telephony.php', "<?php\nreturn [];\n", 'config: config/telephony.php'],
    'a screen' => ['resources/views/operations/communications/inbox.blade.php', 'x', 'screen: resources/views/operations/communications/inbox.blade.php'],
]);

test('a communications route fails', function (): void {
    $root = communicationsFixture();
    file_put_contents($root.'/baseline.json', json_encode(['violations' => []]));
    file_put_contents($root.'/routes.json', json_encode([['method' => 'POST', 'uri' => 'webhooks/communications/twilio/voice/incoming', 'name' => 'webhooks.communications.twilio.voice.incoming']]));

    [$exit, $out] = communicationsCheck(['--root', $root, '--baseline', $root.'/baseline.json', '--routes', $root.'/routes.json']);

    expect($exit)->toBe(1)->and($out)->toContain('route: POST webhooks/communications/twilio/voice/incoming');
});

test('a communications provider package fails', function (): void {
    $root = communicationsFixture();
    file_put_contents($root.'/baseline.json', json_encode(['violations' => []]));
    file_put_contents($root.'/composer.json', json_encode(['require' => ['twilio/sdk' => '^8.0']]));

    [$exit, $out] = communicationsCheck(['--root', $root, '--baseline', $root.'/baseline.json', '--routes', $root.'/routes.json']);

    expect($exit)->toBe(1)->and($out)->toContain('package: composer.json twilio/sdk');
});

test('a fresh install that creates a communications table fails', function (): void {
    $root = communicationsFixture();
    file_put_contents($root.'/baseline.json', json_encode(['violations' => []]));
    file_put_contents($root.'/tables.json', json_encode(['users', 'main.call_sessions']));

    [$exit, $out] = communicationsCheck(['--root', $root, '--baseline', $root.'/baseline.json', '--routes', $root.'/routes.json', '--tables', $root.'/tables.json']);

    expect($exit)->toBe(1)->and($out)->toContain('table: call_sessions');
});

test('a baseline entry that no longer occurs fails until it is removed', function (): void {
    $root = communicationsFixture();
    file_put_contents($root.'/baseline.json', json_encode(['violations' => ['code: app/Ark/Voice/Gone.php']]));

    [$exit, $out] = communicationsCheck(['--root', $root, '--baseline', $root.'/baseline.json', '--routes', $root.'/routes.json']);

    expect($exit)->toBe(1)->and($out)->toContain('no longer occur')->and($out)->toContain('code: app/Ark/Voice/Gone.php');
});

test('a missing baseline or route list fails instead of passing', function (): void {
    $root = communicationsFixture();

    [$noBaseline] = communicationsCheck(['--root', $root, '--baseline', $root.'/missing.json', '--routes', $root.'/routes.json']);
    file_put_contents($root.'/baseline.json', json_encode(['violations' => []]));
    [$noRoutes] = communicationsCheck(['--root', $root, '--baseline', $root.'/baseline.json']);

    expect($noBaseline)->toBe(2)->and($noRoutes)->toBe(2);
});

test('a hand-edited baseline that adds entries the target branch lacks fails', function (): void {
    $root = communicationsFixture();
    mkdir($root.'/app/Ark/Voice', 0777, true);
    file_put_contents($root.'/app/Ark/Voice/Added.php', "<?php\n");
    file_put_contents($root.'/baseline.json', json_encode(['violations' => ['code: app/Ark/Voice/Added.php']]));
    file_put_contents($root.'/previous.json', json_encode(['violations' => []]));
    $args = ['--root', $root, '--baseline', $root.'/baseline.json', '--routes', $root.'/routes.json', '--previous-baseline', $root.'/previous.json'];

    [$exit, $out] = communicationsCheck($args);
    [$missingPrevious] = communicationsCheck(array_merge(array_slice($args, 0, 6), ['--previous-baseline', $root.'/missing.json']));

    expect($exit)->toBe(1)
        ->and($out)->toContain('It may only shrink')
        ->and($out)->toContain('code: app/Ark/Voice/Added.php')
        ->and($missingPrevious)->toBe(2);
});

test('a baseline that shrinks against the target branch passes', function (): void {
    $root = communicationsFixture();
    file_put_contents($root.'/baseline.json', json_encode(['violations' => []]));
    file_put_contents($root.'/previous.json', json_encode(['violations' => ['code: app/Ark/Voice/Moved.php']]));

    [$exit, $out] = communicationsCheck(['--root', $root, '--baseline', $root.'/baseline.json', '--routes', $root.'/routes.json', '--previous-baseline', $root.'/previous.json']);

    expect($exit)->toBe(0, $out);
});

test('the baseline can shrink but never grow', function (): void {
    $root = communicationsFixture();
    mkdir($root.'/app/Ark/Voice', 0777, true);
    file_put_contents($root.'/app/Ark/Voice/Kept.php', "<?php\n");
    file_put_contents($root.'/baseline.json', json_encode(['violations' => ['code: app/Ark/Voice/Kept.php', 'code: app/Ark/Voice/Removed.php']]));
    $args = ['--root', $root, '--baseline', $root.'/baseline.json', '--routes', $root.'/routes.json', '--write-baseline'];

    [$shrink] = communicationsCheck($args);
    $afterShrink = json_decode((string) file_get_contents($root.'/baseline.json'), true)['violations'];
    file_put_contents($root.'/app/Ark/Voice/Added.php', "<?php\n");
    [$grow, $out] = communicationsCheck($args);

    expect($shrink)->toBe(0)
        ->and($afterShrink)->toBe(['code: app/Ark/Voice/Kept.php'])
        ->and($grow)->toBe(1)
        ->and($out)->toContain('Refusing to grow the baseline');
});
