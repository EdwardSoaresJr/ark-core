<?php

use App\Ark\Install\InstallationState;
use App\Ark\Operations\Printing\QzSigningPairGenerator;
use App\Ark\Operations\Printing\QzTraySigning;
use App\Ark\Operations\Printing\QzWorkstationSetup;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Support\Facades\URL;

function qzSetupTempDir(string $label): string
{
    $dir = sys_get_temp_dir().'/ark-qz-'.$label.'-'.bin2hex(random_bytes(4));
    mkdir($dir, 0o700, true);

    return $dir;
}

function qzSetupUseStorage(string $dir): void
{
    app()->useStoragePath($dir);
    InstallationState::markInstalled();
}

function qzSetupShopPair(string $label = 'shop', string $organization = 'Synthetic Printing Shop'): string
{
    $dir = qzSetupTempDir($label);
    qzSetupUseStorage($dir);
    app(QzSigningPairGenerator::class)->generate($organization);

    return $dir.'/'.QzTraySigning::SHOP_DIRECTORY;
}

function qzSetupRun(array $command, array $env, ?string $input = null): array
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env + ['PATH' => getenv('PATH') ?: '/usr/bin:/bin']);
    fwrite($pipes[0], (string) $input);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $out];
}

beforeEach(function (): void {
    $this->originalStorage = storage_path();
    config([
        'printing.qz.certificate_path' => '',
        'printing.qz.private_key_path' => '',
        'printing.qz.trust_root_path' => '',
        'printing.qz.signature_algorithm' => 'sha512',
    ]);
});

afterEach(function (): void {
    app()->useStoragePath($this->originalStorage);
});

test('the shop signs with its own pair from storage and the sign endpoint verifies against it', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    qzSetupShopPair();

    $health = QzTraySigning::healthSnapshot();
    expect($health['ready'])->toBeTrue()
        ->and($health['source'])->toBe(QzTraySigning::SOURCE_SHOP)
        ->and($health['private_key_matches_certificate'])->toBeTrue()
        ->and($health['private_key_permissions_ok'])->toBeTrue()
        ->and($health['chains_to_trust_root'])->toBeTrue()
        ->and($health['certificate']['organization'])->toBe('Synthetic Printing Shop');

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $response = $this->actingAs($advisor)->postJson(route('operations.printing.qz.sign'), ['data' => 'ark-qz-request']);

    $response->assertOk();
    expect(QzTraySigning::verifySignatureLocally('ark-qz-request', $response->json('signature')))->toBeTrue()
        ->and($response->getContent())->not->toContain('PRIVATE KEY');
});

test('the shop folder wins over configured paths and configured paths remain the fallback', function (): void {
    $configured = qzSetupTempDir('configured');
    qzSetupUseStorage($configured);
    app(QzSigningPairGenerator::class)->generate('Configured Pair');
    $configuredDir = $configured.'/'.QzTraySigning::SHOP_DIRECTORY;

    $empty = qzSetupTempDir('empty');
    qzSetupUseStorage($empty);
    config([
        'printing.qz.certificate_path' => $configuredDir.'/'.QzTraySigning::CERTIFICATE_FILE,
        'printing.qz.private_key_path' => $configuredDir.'/'.QzTraySigning::PRIVATE_KEY_FILE,
    ]);

    expect(QzTraySigning::source())->toBe(QzTraySigning::SOURCE_CONFIGURED)
        ->and(QzTraySigning::healthSnapshot()['ready'])->toBeTrue()
        ->and(QzTraySigning::healthSnapshot()['chains_to_trust_root'])->toBeTrue()
        ->and(QzTraySigning::healthSnapshot()['certificate']['organization'])->toBe('Configured Pair');

    config(['printing.qz.certificate_path' => '', 'printing.qz.private_key_path' => '']);
    qzSetupShopPair('own', 'Own Pair');
    config([
        'printing.qz.certificate_path' => $configuredDir.'/'.QzTraySigning::CERTIFICATE_FILE,
        'printing.qz.private_key_path' => $configuredDir.'/'.QzTraySigning::PRIVATE_KEY_FILE,
    ]);

    expect(QzTraySigning::source())->toBe(QzTraySigning::SOURCE_SHOP)
        ->and(QzTraySigning::healthSnapshot()['certificate']['organization'])->toBe('Own Pair');
});

test('a key that does not belong to the certificate is not ready, is explained, and refuses to sign', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $other = qzSetupShopPair('other');
    $otherKey = file_get_contents($other.'/'.QzTraySigning::PRIVATE_KEY_FILE);
    $dir = qzSetupShopPair('mismatch');
    file_put_contents($dir.'/'.QzTraySigning::PRIVATE_KEY_FILE, $otherKey);

    $health = QzTraySigning::healthSnapshot();
    expect($health['ready'])->toBeFalse()
        ->and($health['private_key_valid'])->toBeTrue()
        ->and($health['private_key_matches_certificate'])->toBeFalse()
        ->and($health['private_key_error_hint'])->toContain('does not belong');

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($admin)->postJson(route('operations.printing.qz.sign'), ['data' => 'x'])->assertStatus(501);
    $this->artisan('ark:printing:qz-check')
        ->expectsOutputToContain('private_key_matches_certificate=no')
        ->doesntExpectOutputToContain('PRIVATE KEY')
        ->assertFailed();
});

test('a shop folder missing its key says so without naming server paths', function (): void {
    $dir = qzSetupShopPair('partial');
    unlink($dir.'/'.QzTraySigning::PRIVATE_KEY_FILE);

    $health = QzTraySigning::healthSnapshot();
    expect($health['ready'])->toBeFalse()
        ->and($health['source'])->toBe(QzTraySigning::SOURCE_SHOP)
        ->and($health['private_key_error_hint'])->toContain('no private key')
        ->and($health['private_key_error_hint'])->not->toContain(sys_get_temp_dir());
});

test('an expired signing certificate is not ready', function (): void {
    $dir = qzSetupTempDir('expired').'/'.QzTraySigning::SHOP_DIRECTORY;
    mkdir($dir, 0o700, true);
    qzSetupUseStorage(dirname($dir, 3));
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'ARK Printing Signing'], $key, ['digest_alg' => 'sha256']);
    $cert = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
    openssl_x509_export($cert, $certPem);
    openssl_pkey_export($key, $keyPem);
    file_put_contents($dir.'/'.QzTraySigning::CERTIFICATE_FILE, $certPem);
    file_put_contents($dir.'/'.QzTraySigning::PRIVATE_KEY_FILE, $keyPem);
    chmod($dir.'/'.QzTraySigning::PRIVATE_KEY_FILE, 0o600);
    expect(QzTraySigning::healthSnapshot()['ready'])->toBeTrue();

    $this->travel(3)->days();
    $health = QzTraySigning::healthSnapshot();
    expect($health['private_key_matches_certificate'])->toBeTrue()
        ->and($health['certificate_in_validity_window'])->toBeFalse()
        ->and($health['ready'])->toBeFalse()
        ->and($health['certificate_error_hint'])->toContain('expired');
});

test('a private key readable by other accounts is reported but still signs', function (): void {
    $dir = qzSetupShopPair('mode');
    chmod($dir.'/'.QzTraySigning::PRIVATE_KEY_FILE, 0o664);

    $health = QzTraySigning::healthSnapshot();
    expect($health['ready'])->toBeTrue()
        ->and($health['private_key_permissions_ok'])->toBeFalse();
    $this->artisan('ark:printing:qz-check')->expectsOutputToContain('private_key_mode=readable by other accounts (chmod 600)')->assertSuccessful();
})->skip(DIRECTORY_SEPARATOR !== '/', 'POSIX file modes only');

test('a cached signature is never served for another signing pair', function (): void {
    config(['cache.default' => 'array', 'printing.qz.sign_cache_ttl' => 300]);
    qzSetupShopPair('cache-a');
    $first = QzTraySigning::signBase64('same-payload');

    qzSetupShopPair('cache-b');
    $second = QzTraySigning::signBase64('same-payload');

    expect($second)->not->toBe($first)
        ->and(QzTraySigning::verifySignatureLocally('same-payload', $second))->toBeTrue();
});

test('the generator creates a working pair once and never replaces one', function (): void {
    $dir = qzSetupTempDir('generate');
    qzSetupUseStorage($dir);
    ShopSettings::current()->update(['shop_name' => 'Synthetic Generator Shop']);
    ShopSettings::forgetCurrent();

    $this->artisan('ark:printing:qz-certificate --generate')
        ->expectsOutputToContain('certificate=ARK Printing Signing / Synthetic Generator Shop')
        ->doesntExpectOutputToContain('PRIVATE KEY')
        ->assertSuccessful();

    $folder = $dir.'/'.QzTraySigning::SHOP_DIRECTORY;
    $before = array_map(fn (string $f): string => hash_file('sha256', $folder.'/'.$f), [QzTraySigning::CERTIFICATE_FILE, QzTraySigning::PRIVATE_KEY_FILE, QzTraySigning::TRUST_ROOT_FILE]);
    expect(fileperms($folder.'/'.QzTraySigning::PRIVATE_KEY_FILE) & 0o777)->toBe(0o600)
        ->and(fileperms($folder) & 0o777)->toBe(0o700)
        ->and(file_get_contents($folder.'/'.QzTraySigning::TRUST_ROOT_FILE))->not->toContain('PRIVATE KEY')
        ->and(glob($folder.'/*'))->toHaveCount(3);

    $this->artisan('ark:printing:qz-certificate --generate')->expectsOutputToContain('Nothing was changed')->assertFailed();

    $after = array_map(fn (string $f): string => hash_file('sha256', $folder.'/'.$f), [QzTraySigning::CERTIFICATE_FILE, QzTraySigning::PRIVATE_KEY_FILE, QzTraySigning::TRUST_ROOT_FILE]);
    expect($after)->toBe($before);
});

test('the workstation setup downloads carry only the public trust root and its fingerprint', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $dir = qzSetupShopPair('download');
    $root = QzTraySigning::describeCertificate((string) file_get_contents($dir.'/'.QzTraySigning::TRUST_ROOT_FILE));
    $keyBody = trim(preg_replace('/-----[A-Z ]+-----/', '', (string) file_get_contents($dir.'/'.QzTraySigning::PRIVATE_KEY_FILE)));
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    foreach (['windows' => '.cmd', 'macos' => '.sh'] as $platform => $extension) {
        $response = $this->actingAs($advisor)->get(route('operations.printing.setup.download', ['platform' => $platform]));
        $response->assertOk();
        $body = $response->getContent();

        expect($response->headers->get('Content-Disposition'))->toContain('attachment')
            ->and($response->headers->get('Content-Disposition'))->toContain(QzWorkstationSetup::VERSION.$extension)
            ->and($body)->toContain($root['sha256'])
            ->and($body)->toContain('authcert.override')
            ->and($body)->not->toContain('PRIVATE KEY')
            ->and($body)->not->toContain(substr(str_replace("\n", '', $keyBody), 0, 64))
            ->and($body)->not->toContain('@@ARK_');
    }

    $windows = $this->actingAs($advisor)->get(route('operations.printing.setup.download', ['platform' => 'windows']))->getContent();
    expect(substr_count($windows, "\r\n"))->toBe(substr_count($windows, "\n"))
        ->and($windows)->toStartWith('<# :');
});

test('the setup download needs a working pair, staff access, and a valid signature for the Terminal link', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    qzSetupUseStorage(qzSetupTempDir('nopair'));
    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);

    $this->actingAs($advisor)->get(route('operations.printing.setup.download', ['platform' => 'macos']))->assertStatus(409);
    auth()->logout();

    qzSetupShopPair('signed');
    $this->get(route('operations.printing.setup.download', ['platform' => 'macos']))->assertRedirect();

    $signed = URL::temporarySignedRoute('operations.printing.setup.signed-download', now()->addMinutes(30), ['platform' => 'macos']);
    $this->get($signed)->assertOk()->assertHeader('Content-Type', 'text/x-shellscript; charset=UTF-8');
    $this->get($signed.'x')->assertForbidden();
    $this->get(route('operations.printing.setup.signed-download', ['platform' => 'macos']))->assertForbidden();
    $this->get(URL::temporarySignedRoute('operations.printing.setup.signed-download', now()->subMinute(), ['platform' => 'macos']))->assertForbidden();
});

test('Set Up Printing shows the fingerprint to staff and printer saving only to managers', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    $dir = qzSetupShopPair('page');
    $root = QzTraySigning::describeCertificate((string) file_get_contents($dir.'/'.QzTraySigning::TRUST_ROOT_FILE));
    $fingerprint = implode(':', str_split(strtoupper($root['sha256']), 2));

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor)->get(route('operations.printing.setup'))
        ->assertOk()
        ->assertSee('Download for Windows')
        ->assertSee($fingerprint)
        ->assertSee('sudo bash /tmp/ark-print-setup.sh', false)
        ->assertDontSee('Use for key tags')
        ->assertDontSee('PRIVATE KEY');

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($admin)->get(route('operations.printing.setup'))->assertOk()->assertSee('Use for key tags');

    qzSetupUseStorage(qzSetupTempDir('page-none'));
    $this->actingAs($advisor)->get(route('operations.printing.setup'))
        ->assertOk()
        ->assertSee('not ready on the ARK server')
        ->assertDontSee('Download for Windows');
});

test('saving a printer from Set Up Printing changes only printer names and turns QZ printing on', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);
    ShopSettings::current()->update(['qz_printing_enabled' => false, 'qz_key_tag_orientation' => 'landscape', 'qz_printing_oil_sticker_printer' => 'Back office QL']);
    ShopSettings::forgetCurrent();

    $advisor = User::factory()->create()->assignRole(ArkRole::Advisor->value);
    $this->actingAs($advisor)->patchJson(route('operations.printing.setup.printers'), ['qz_printing_key_tag_printer' => 'Front QL'])->assertForbidden();

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);
    $this->actingAs($admin)->patchJson(route('operations.printing.setup.printers'), ['qz_printing_key_tag_printer' => ' Front QL '])
        ->assertOk()
        ->assertJsonPath('key_tag_printer', 'Front QL')
        ->assertJsonPath('oil_sticker_printer', 'Back office QL');

    ShopSettings::forgetCurrent();
    $settings = ShopSettings::current();
    expect($settings->qz_printing_key_tag_printer)->toBe('Front QL')
        ->and($settings->qz_printing_oil_sticker_printer)->toBe('Back office QL')
        ->and($settings->qz_key_tag_orientation)->toBe('landscape')
        ->and((bool) $settings->qz_printing_enabled)->toBeTrue();
});

test('the test label is synthetic and print failures point to Set Up Printing', function (): void {
    $html = view('operations.printing.test-label', ['shopName' => 'Synthetic Label Shop', 'printedAt' => '2026-11-06 12:00', 'labelWidthMm' => 62.0, 'labelHeightMm' => 38.1])->render();
    expect($html)->toContain('ARK printing test')->toContain('Synthetic Label Shop')->toContain('Not a customer document');

    $this->get(route('operations.printing.test-label'))->assertRedirect();

    $helpers = (string) file_get_contents(resource_path('views/components/operations/print-helpers.blade.php'));
    expect($helpers)->toContain("route('operations.printing.setup')")
        ->and($helpers)->not->toContain('Approve printing in QZ Tray if prompted');
});

/*
 * The setup scripts, run for real against a copy of QZ Tray's file layout.
 */
function qzSetupFixture(string $properties): array
{
    $base = qzSetupTempDir('workstation');
    mkdir($base.'/QZ Tray.app/Contents/Resources', 0o755, true);
    mkdir($base.'/shared');
    mkdir($base.'/home');
    file_put_contents($base.'/QZ Tray.app/Contents/Info.plist', '<plist version="1.0"><dict><key>CFBundleShortVersionString</key><string>2.2.5</string></dict></plist>');
    file_put_contents($base.'/QZ Tray.app/Contents/Resources/qz-tray.properties', $properties);

    return [$base, $base.'/QZ Tray.app/Contents/Resources/qz-tray.properties', [
        'ARK_QZ_APP' => $base.'/QZ Tray.app',
        'ARK_TRUST_DIR' => $base.'/trust',
        'ARK_QZ_SHARED_DIR' => $base.'/shared',
        'ARK_CONSOLE_USER' => get_current_user(),
        'ARK_USER_HOME' => $base.'/home',
        'ARK_NO_RESTART' => '1',
        'ARK_REQUIRE_ROOT' => '0',
    ]];
}

function qzSetupMacScript(string $host = 'synthetic-shop.arksms.test'): string
{
    $path = sys_get_temp_dir().'/ark-print-setup-'.bin2hex(random_bytes(4)).'.sh';
    file_put_contents($path, QzWorkstationSetup::render('macos', $host, 'https://'.$host.'/app/printing/setup'));

    return $path;
}

test('the macOS setup adds one entry, keeps every other setting, repeats safely, and uninstalls cleanly', function (): void {
    $dir = qzSetupShopPair('mac');
    $root = QzTraySigning::describeCertificate((string) file_get_contents($dir.'/'.QzTraySigning::TRUST_ROOT_FILE));
    $original = "#QZ\nwss.host=0.0.0.0\nauthcert.override=C\\:\\\\Other App\\\\root^;x.crt;/opt/other/root.crt|opt\nfile.allow=/tmp|Some App|\n";
    [$base, $props, $env] = qzSetupFixture($original);
    $script = qzSetupMacScript();

    expect(qzSetupRun(['bash', '-n', $script], [])[0])->toBe(0);

    [$code, $out] = qzSetupRun(['bash', $script], $env);
    $trustFile = $base.'/trust/ark-synthetic-shop.arksms.test-'.substr($root['sha256'], 0, 16).'.crt';
    expect($code)->toBe(0, $out)
        ->and($out)->toContain('Added this shop')
        ->and(file_get_contents($props))->toBe("#QZ\nwss.host=0.0.0.0\nauthcert.override=C\\:\\\\Other App\\\\root^;x.crt;/opt/other/root.crt|opt;{$trustFile}\nfile.allow=/tmp|Some App|\n")
        ->and(file_get_contents($trustFile))->toContain('BEGIN CERTIFICATE')
        ->and(glob($props.'.ark-backup-*'))->toHaveCount(1);

    $afterFirst = file_get_contents($props);
    [$code, $out] = qzSetupRun(['bash', $script], $env);
    expect($code)->toBe(0)->and($out)->toContain('Already set up')->and(file_get_contents($props))->toBe($afterFirst)
        ->and(glob($props.'.ark-backup-*'))->toHaveCount(1);

    [$code, $out] = qzSetupRun(['bash', $script, '--status'], $env);
    expect($code)->toBe(0)->and($out)->toContain('set to trust this shop')->and($out)->toContain('/opt/other/root.crt');

    [$code, $out] = qzSetupRun(['bash', $script, '--uninstall'], $env);
    expect($code)->toBe(0, $out)->and(file_get_contents($props))->toBe($original)->and(file_exists($trustFile))->toBeFalse();

    [$code] = qzSetupRun(['bash', $script, '--status'], $env);
    expect($code)->toBe(1);
})->skip(fn (): bool => trim((string) shell_exec('command -v bash')) === '' || trim((string) shell_exec('command -v openssl')) === '', 'needs bash and openssl');

test('the macOS setup replaces only ARK\'s previous root for the same shop and refuses layouts it cannot edit safely', function (): void {
    qzSetupShopPair('mac-old');
    $oldScript = qzSetupMacScript();
    $otherShopScript = qzSetupMacScript('other-shop.arksms.test');
    [$base, $props, $env] = qzSetupFixture("authcert.override=/opt/vendor/root.crt\n");
    expect(qzSetupRun(['bash', $oldScript], $env)[0])->toBe(0);
    expect(qzSetupRun(['bash', $otherShopScript], $env)[0])->toBe(0);
    $oldFiles = glob($base.'/trust/ark-synthetic-shop.arksms.test-*.crt');

    qzSetupShopPair('mac-new');
    $newScript = qzSetupMacScript();
    [$code, $out] = qzSetupRun(['bash', $newScript], $env);
    $value = explode(';', trim(substr((string) file_get_contents($props), strlen('authcert.override='))));

    expect($code)->toBe(0, $out)
        ->and($out)->toContain("Replaced ARK's previous certificate for this shop")
        ->and($value)->toHaveCount(3)
        ->and($value[0])->toBe('/opt/vendor/root.crt')
        ->and($value[1])->toContain('ark-other-shop.arksms.test-')
        ->and($value[2])->toContain('ark-synthetic-shop.arksms.test-')
        ->and(file_exists($oldFiles[0]))->toBeFalse();

    foreach ([
        "authcert.override=/a.crt;\\\n /b.crt\n" => 'several lines',
        "authcert.override=/a.crt\nauthcert.override=/b.crt\n" => 'more than once',
        "trustedRootCert=/a.crt\n" => 'trustedRootCert',
    ] as $layout => $message) {
        [, $refused, $refusedEnv] = qzSetupFixture($layout);
        [$code, $out] = qzSetupRun(['bash', $newScript], $refusedEnv);
        expect($code)->toBe(8)->and($out)->toContain($message)->and(file_get_contents($refused))->toBe($layout);
    }

    $tampered = (string) file_get_contents($newScript);
    $lines = explode("\n", $tampered);
    $pemLine = array_search('-----BEGIN CERTIFICATE-----', $lines, true) + 2;
    $lines[$pemLine] = strrev($lines[$pemLine]);
    file_put_contents($newScript, implode("\n", $lines));
    [, $untouched, $tamperEnv] = qzSetupFixture("wss.host=0.0.0.0\n");
    [$code, $out] = qzSetupRun(['bash', $newScript], $tamperEnv);
    expect($code)->toBe(6)->and(file_get_contents($untouched))->toBe("wss.host=0.0.0.0\n");

    [, , $missingEnv] = qzSetupFixture('');
    $missingEnv['ARK_QZ_APP'] .= '-missing';
    expect(qzSetupRun(['bash', $oldScript], $missingEnv)[0])->toBe(3);

    [$oldBase, , $oldEnv] = qzSetupFixture("wss.host=0.0.0.0\n");
    file_put_contents($oldBase.'/QZ Tray.app/Contents/Info.plist', '<plist><dict><key>CFBundleShortVersionString</key><string>2.1.2</string></dict></plist>');
    expect(qzSetupRun(['bash', $oldScript], $oldEnv)[0])->toBe(4);
})->skip(fn (): bool => trim((string) shell_exec('command -v bash')) === '' || trim((string) shell_exec('command -v openssl')) === '', 'needs bash and openssl');

test('the Windows setup edits qz-tray.properties the same way and keeps CRLF line endings', function (): void {
    $pwsh = getenv('ARK_TEST_PWSH') ?: trim((string) shell_exec('command -v pwsh'));
    $dir = qzSetupShopPair('win');
    $root = QzTraySigning::describeCertificate((string) file_get_contents($dir.'/'.QzTraySigning::TRUST_ROOT_FILE));
    $script = sys_get_temp_dir().'/ark-print-setup-'.bin2hex(random_bytes(4)).'.cmd';
    file_put_contents($script, QzWorkstationSetup::render('windows', 'synthetic-shop.arksms.test', 'https://synthetic-shop.arksms.test/app/printing/setup'));

    $base = qzSetupTempDir('win-layout');
    mkdir($base.'/QZ Tray');
    $original = "#QZ\r\nwss.host=0.0.0.0\r\nauthcert.override=C\\:\\\\Program Files\\\\Other\\\\root^;x.crt;C\\:/Vendor/root.crt|opt\r\n";
    file_put_contents($base.'/QZ Tray/qz-tray.properties', $original);
    $env = [
        'ARK_SETUP_SELF' => $script, 'ARK_QZ_DIR' => $base.'/QZ Tray', 'ARK_QZ_VERSION' => '2.2.5', 'ARK_TRUST_DIR' => $base.'/trust',
        'ARK_QZ_SHARED_DIR' => $base.'/shared', 'ARK_QZ_USER_DIR' => $base.'/user', 'ARK_NO_RESTART' => '1', 'ARK_REQUIRE_ADMIN' => '0', 'HOME' => $base,
    ];
    $run = fn (string $args = ''): array => qzSetupRun([$pwsh, '-NoProfile', '-Command', '& ([ScriptBlock]::Create([IO.File]::ReadAllText($env:ARK_SETUP_SELF))) '.$args], $env);

    [$code, $out] = $run();
    $token = $base.'/trust/ark-synthetic-shop.arksms.test-'.substr($root['sha256'], 0, 16).'.crt';
    expect($code)->toBe(0, $out)
        ->and(file_get_contents($base.'/QZ Tray/qz-tray.properties'))->toBe("#QZ\r\nwss.host=0.0.0.0\r\nauthcert.override=C\\:\\\\Program Files\\\\Other\\\\root^;x.crt;C\\:/Vendor/root.crt|opt;{$token}\r\n");

    [$code, $out] = $run();
    expect($code)->toBe(0)->and($out)->toContain('Already set up');
    expect($run('-Status')[0])->toBe(0);

    [$code, $out] = $run('-Uninstall');
    expect($code)->toBe(0, $out)->and(file_get_contents($base.'/QZ Tray/qz-tray.properties'))->toBe($original);
    expect($run('-Status')[0])->toBe(1);
})->skip(fn (): bool => (getenv('ARK_TEST_PWSH') ?: trim((string) shell_exec('command -v pwsh'))) === '', 'needs PowerShell (pwsh); set ARK_TEST_PWSH');
