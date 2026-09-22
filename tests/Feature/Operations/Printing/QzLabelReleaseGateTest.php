<?php

use App\Ark\Operations\Printing\QzTraySigning;
use App\Ark\Operations\Printing\ShopPrintingSettings;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
function qzReleasePair(string $dir): void
{
    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    expect($key)->not->toBeFalse();

    $csr = openssl_csr_new(['commonName' => 'ARK QZ test'], $key, ['digest_alg' => 'sha512']);
    expect($csr)->not->toBeFalse();

    $cert = openssl_csr_sign($csr, null, $key, 2, ['digest_alg' => 'sha512']);
    expect($cert)->not->toBeFalse();

    expect(openssl_pkey_export($key, $privatePem))->toBeTrue();
    expect(openssl_x509_export($cert, $certificatePem))->toBeTrue();

    file_put_contents($dir.'/digital-certificate.txt', $certificatePem);
    file_put_contents($dir.'/private-key.pem', $privatePem);
    chmod($dir.'/private-key.pem', 0600);
}

function qzReleaseConfigure(string $dir): void
{
    config([
        'printing.qz.certificate_path' => $dir.'/digital-certificate.txt',
        'printing.qz.private_key_path' => $dir.'/private-key.pem',
        'printing.qz.private_key_passphrase' => '',
        'printing.qz.signature_algorithm' => 'sha512',
    ]);
}

test('the image build keeps the QZ Tray client the browser loads', function (): void {
    foreach ([
        public_path('vendor/qz/qz-tray.js'),
        public_path('js/ark/qz-tray.js'),
    ] as $path) {
        expect(is_file($path))->toBeTrue()
            ->and(filesize($path))->toBeGreaterThan(10_000)
            ->and(file_get_contents($path))->toContain('_qz.security');
    }

    $helpers = (string) file_get_contents(resource_path('views/components/operations/print-helpers.blade.php'));

    expect($helpers)->toContain("asset('vendor/qz/qz-tray.js')")
        ->and($helpers)->toContain('setCertificatePromise')
        ->and($helpers)->toContain('setSignaturePromise')
        ->and($helpers)->toContain('operations.printing.qz.sign');

    $ignore = (string) file_get_contents(base_path('.dockerignore'));
    $lines = preg_split('/\R/', $ignore) ?: [];

    expect($lines)->not->toContain('vendor')
        ->and($lines)->toContain('/vendor')
        ->and($lines)->toContain('!public/vendor');

    $dockerfile = (string) file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)->toContain('test -f /app/public/js/ark/qz-tray.js')
        ->and($dockerfile)->toContain('test -f /app/public/vendor/qz/qz-tray.js')
        ->and($dockerfile)->toContain('test -f /app/public/vendor/pdfjs/pdf.min.js')
        ->and($dockerfile)->toContain('test -f /app/public/vendor/pdfjs/pdf.worker.min.js');

    $helpers = (string) file_get_contents(resource_path('views/components/operations/print-helpers.blade.php'));
    $printing = (string) file_get_contents(base_path('config/printing.php'));
    $renderer = (string) file_get_contents(app_path('Ark/Operations/Printing/LabelPdfRenderer.php'));

    expect($helpers)->toContain("asset('vendor/pdfjs')")
        ->and($helpers)->not->toContain('cdn.jsdelivr.net/npm/pdfjs-dist')
        ->and($printing)->toContain("env('PRINTING_QL_FORCE_RASTER', 'true')")
        ->and($renderer)->not->toContain('waitUntilNetworkIdle')
        ->and(is_file(public_path('vendor/pdfjs/pdf.min.js')))->toBeTrue()
        ->and(is_file(public_path('vendor/pdfjs/pdf.worker.min.js')))->toBeTrue();

    $publish = (string) file_get_contents(base_path('scripts/assert-canonical-core-publish.sh'));

    expect($publish)->toContain('public/vendor/qz/qz-tray.js')
        ->and($publish)->toContain('public/js/ark/qz-tray.js')
        ->and($publish)->toContain('public/vendor/pdfjs/pdf.min.js')
        ->and($publish)->toContain('public/vendor/pdfjs/pdf.worker.min.js')
        ->and($publish)->toContain('Dockerfile does not fail the build when the QZ client is missing');
});

test('key tag printing keeps the Brother QL page and printer', function (): void {
    expect(ShopPrintingSettings::keyTagPrinter())->toBe('Brother QL-800')
        ->and(ShopPrintingSettings::keyTagQzPage()['width_mm'])->toEqual(62.0)
        ->and(ShopPrintingSettings::keyTagQzPage()['height_mm'])->toEqual(38.1);

    ShopSettings::current()->update([
        'qz_printing_key_tag_printer' => 'Front counter QL',
    ]);
    ShopSettings::forgetCurrent();

    expect(ShopPrintingSettings::keyTagPrinter())->toBe('Front counter QL');
});

test('signing a print request verifies and does not return the private key', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);

    $dir = sys_get_temp_dir().'/ark-qz-gate-'.bin2hex(random_bytes(4));
    mkdir($dir);
    qzReleasePair($dir);
    qzReleaseConfigure($dir);

    $payload = 'ark-qz-print-request';
    $signature = QzTraySigning::signBase64($payload);

    expect(QzTraySigning::verifySignatureLocally($payload, $signature))->toBeTrue()
        ->and($signature)->not->toContain('PRIVATE KEY');

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $response = $this->actingAs($admin)->postJson(route('operations.printing.qz.sign'), [
        'data' => $payload,
    ]);

    $response->assertOk();
    $body = $response->json();

    expect($body)->toHaveKey('signature')
        ->and($body['signature'])->toBeString()
        ->and(json_encode($body))->not->toContain('PRIVATE KEY')
        ->and(QzTraySigning::verifySignatureLocally($payload, $body['signature']))->toBeTrue();

    $health = $this->actingAs($admin)->getJson(route('operations.printing.qz.sign-health'));

    $health->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('algorithm', 'sha512');

    expect(json_encode($health->json()))->not->toContain('PRIVATE KEY');
});

test('sign health is a real route and refuses to sign when configuration is missing', function (): void {
    $this->seed(ArkAuthorizationSeeder::class);

    $this->get('/app/api/qz/sign-health')->assertRedirect();

    config([
        'printing.qz.certificate_path' => '',
        'printing.qz.private_key_path' => '',
    ]);

    $admin = User::factory()->create()->assignRole(ArkRole::Admin->value);

    $this->actingAs($admin)
        ->postJson(route('operations.printing.qz.sign'), ['data' => 'ark-qz-print-request'])
        ->assertStatus(501);

    $this->artisan('ark:printing:qz-check')
        ->expectsOutputToContain('signing_ready=no')
        ->doesntExpectOutputToContain('PRIVATE KEY')
        ->assertFailed();
});

test('qz check passes when the client and a signing pair are present', function (): void {
    $dir = sys_get_temp_dir().'/ark-qz-gate-'.bin2hex(random_bytes(4));
    mkdir($dir);
    qzReleasePair($dir);
    qzReleaseConfigure($dir);

    $this->artisan('ark:printing:qz-check')
        ->expectsOutputToContain('qz_client=present')
        ->expectsOutputToContain('signing_ready=yes')
        ->doesntExpectOutputToContain('PRIVATE KEY')
        ->assertSuccessful();
});
