<?php

use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Runtime\Exceptions\ErrorPagePresenter;
use App\Ark\Runtime\Exceptions\ExceptionReportArchive;
use App\Ark\Runtime\Exceptions\ExceptionReporter;
use App\Ark\Runtime\Surfaces\PublicRootUrlConfigurator;
use App\Mail\ExceptionOccurredMail;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

test('missing pages render a friendly public error screen', function () {
    config(['app.debug' => false]);

    $this->get('/does-not-exist-'.uniqid())
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertSee('Return Home')
        ->assertDontSee('Symfony\Component');
});

test('operations routes render workboard recovery actions on errors', function () {
    config(['app.debug' => false]);

    $this->get('/app/does-not-exist-'.uniqid())
        ->assertNotFound()
        ->assertSee('Return To Workboard');
});

test('server errors render a calm customer page when debug is off', function () {
    config(['app.debug' => false]);

    // POST: the public legacy-redirect catch-all swallows unknown GET paths,
    // so a GET route registered inside the test can never match.
    Route::post('/__test/server-error', fn () => abort(500));

    $this->post('/__test/server-error')
        ->assertStatus(500)
        ->assertSee('Something went wrong')
        ->assertSee('The shop has been notified');
});

test('exception report copy url uses app domain when app url is localhost', function () {
    Mail::fake();
    Cache::flush();

    config([
        'app.url' => 'http://localhost',
        'ark-ecosystem.operations_url' => 'http://localhost',
        'surfaces.enabled' => true,
        'surfaces.app' => 'app.demo-auto.test',
        'errors.report.enabled' => true,
        'errors.report.email' => 'alerts@example.test',
        'errors.report.queue' => false,
        'errors.report.throttle_seconds' => 0,
    ]);

    PublicRootUrlConfigurator::apply();

    app(ExceptionReporter::class)->notify(new RuntimeException('Public URL fallback'));

    Mail::assertSent(ExceptionOccurredMail::class, function (ExceptionOccurredMail $mail): bool {
        return str_starts_with((string) $mail->context['report_copy_url'], 'https://app.demo-auto.test/');
    });
});

test('reportable server exceptions email configured staff', function () {
    Mail::fake();
    Cache::flush();

    config([
        'errors.report.enabled' => true,
        'errors.report.email' => 'alerts@example.test',
        'errors.report.queue' => false,
        'errors.report.throttle_seconds' => 0,
    ]);

    app(Handler::class)->report(new RuntimeException('Estimate snapshot failed'));

    Mail::assertSent(ExceptionOccurredMail::class, function (ExceptionOccurredMail $mail): bool {
        return $mail->hasTo('alerts@example.test')
            && $mail->context['exception_message'] === 'Estimate snapshot failed'
            && filled($mail->context['report_id'] ?? null)
            && filled($mail->context['report_markdown'] ?? null)
            && filled($mail->context['report_copy_url'] ?? null);
    });
});

test('exception report email includes markdown attachment and copy page', function () {
    Mail::fake();
    Cache::flush();

    config([
        'errors.report.enabled' => true,
        'errors.report.email' => 'alerts@example.test',
        'errors.report.queue' => false,
        'errors.report.throttle_seconds' => 0,
        'errors.report.file.enabled' => false,
    ]);

    app(ExceptionReporter::class)->notify(new RuntimeException('Copy page payload'));

    Mail::assertSent(ExceptionOccurredMail::class, function (ExceptionOccurredMail $mail): bool {
        return count($mail->attachments()) === 1;
    });

    $sent = Mail::sent(ExceptionOccurredMail::class)->first();
    $reportId = (string) $sent->context['report_id'];

    $this->get($sent->context['report_copy_url'])
        ->assertOk()
        ->assertSee('Copy markdown', false)
        ->assertSee($reportId, false)
        ->assertSee('Copy page payload', false)
        ->assertSee('php artisan errors:recent --id='.$reportId, false);
});

test('queued exception report mail stays serializable', function () {
    Mail::fake();
    Cache::flush();

    config([
        'errors.report.enabled' => true,
        'errors.report.email' => 'alerts@example.test',
        'errors.report.queue' => true,
        'errors.report.throttle_seconds' => 0,
    ]);

    app(ExceptionReporter::class)->notify(new RuntimeException('Worksheet broadcast failed'));

    Mail::assertQueued(ExceptionOccurredMail::class, function (ExceptionOccurredMail $mail): bool {
        return $mail->hasTo('alerts@example.test')
            && $mail->context['exception_message'] === 'Worksheet broadcast failed';
    });
});

test('expected client errors are not emailed', function () {
    Mail::fake();
    Cache::flush();

    config([
        'errors.report.enabled' => true,
        'errors.report.email' => 'alerts@example.test',
        'errors.report.queue' => false,
    ]);

    app(Handler::class)->report(new Symfony\Component\HttpKernel\Exception\NotFoundHttpException);

    Mail::assertNothingSent();
});

test('duplicate exception alerts are throttled', function () {
    Mail::fake();
    Cache::flush();

    config([
        'errors.report.enabled' => true,
        'errors.report.email' => 'alerts@example.test',
        'errors.report.queue' => false,
        'errors.report.throttle_seconds' => 300,
    ]);

    $reporter = app(ExceptionReporter::class);
    $exception = new RuntimeException('Repeated failure');

    $reporter->notify($exception);
    $reporter->notify($exception);

    Mail::assertSentCount(1);
});

test('reportable server exceptions are archived as structured json files', function () {
    Mail::fake();
    Cache::flush();

    $directory = storage_path('logs/reported-errors-test-'.uniqid());
    config([
        'errors.report.enabled' => true,
        'errors.report.email' => 'alerts@example.test',
        'errors.report.queue' => false,
        'errors.report.throttle_seconds' => 0,
        'errors.report.file.enabled' => true,
        'errors.report.file.path' => $directory,
        'errors.report.file.retention_days' => 30,
    ]);

    app(ExceptionReporter::class)->notify(new RuntimeException('Archive me for review'));

    expect($directory.'/latest.json')->toBeFile()
        ->and($directory.'/_index.json')->toBeFile();

    $latest = json_decode(file_get_contents($directory.'/latest.json'), true);

    expect($latest['exception_message'])->toBe('Archive me for review')
        ->and($latest['id'])->toBeString()->not->toBe('')
        ->and($latest['report_markdown'] ?? '')->toContain('Report ID')
        ->and($latest['trace'])->toBeArray();

    $this->artisan('errors:recent', ['--limit' => 5])
        ->assertSuccessful()
        ->expectsOutputToContain('Archive me for review');

    config(['errors.report.file.path' => $directory]);

    $found = app(ExceptionReportArchive::class)->findById($latest['id']);

    expect($found)->not->toBeNull()
        ->and($found['report_markdown'] ?? '')->toContain($latest['id']);
});

test('throttled email still writes a file archive entry each time', function () {
    Mail::fake();
    Cache::flush();

    $directory = storage_path('logs/reported-errors-test-'.uniqid());
    config([
        'errors.report.enabled' => true,
        'errors.report.email' => 'alerts@example.test',
        'errors.report.queue' => false,
        'errors.report.throttle_seconds' => 300,
        'errors.report.file.enabled' => true,
        'errors.report.file.path' => $directory,
    ]);

    $reporter = app(\App\Ark\Runtime\Exceptions\ExceptionReporter::class);
    $exception = new RuntimeException('Repeated archive');

    $reporter->notify($exception);
    $reporter->notify($exception);

    Mail::assertSentCount(1);

    $index = json_decode(file_get_contents($directory.'/_index.json'), true);

    expect($index)->toHaveCount(2);
});

test('exception reporter redacts sensitive request input', function () {
    $reporter = app(ExceptionReporter::class);

    $redacted = $reporter->redactInput([
        'shop_name' => 'Auto Repair Keeper',
        'password' => 'super-secret',
        'password_confirmation' => 'super-secret',
        'current_password' => 'old-secret',
        'square_access_token' => 'square-live-token',
        'postmark_token' => 'postmark-live-token',
        'ark_mail_credential' => 'arkmail-secret',
        'cloud_credential' => 'cloud-secret',
        'messenger_page_access_token' => 'meta-live-token',
        'integrations' => [
            'webhook_secret' => 'nested-secret',
            'shop_phone' => '555-0100',
        ],
    ]);

    expect($redacted['shop_name'])->toBe('Auto Repair Keeper')
        ->and($redacted['password'])->toBe('[redacted]')
        ->and($redacted['password_confirmation'])->toBe('[redacted]')
        ->and($redacted['current_password'])->toBe('[redacted]')
        ->and($redacted['square_access_token'])->toBe('[redacted]')
        ->and($redacted['postmark_token'])->toBe('[redacted]')
        ->and($redacted['ark_mail_credential'])->toBe('[redacted]')
        ->and($redacted['cloud_credential'])->toBe('[redacted]')
        ->and($redacted['messenger_page_access_token'])->toBe('[redacted]')
        ->and($redacted['integrations']['webhook_secret'])->toBe('[redacted]')
        ->and($redacted['integrations']['shop_phone'])->toBe('555-0100');
});

test('exception reporter context redacts sensitive input from the current request', function () {
    Mail::fake();
    Cache::flush();

    config([
        'errors.report.enabled' => true,
        'errors.report.email' => 'alerts@example.test',
        'errors.report.queue' => false,
        'errors.report.throttle_seconds' => 0,
    ]);

    $request = Request::create('/app/settings/shop', 'POST', [
        'shop_name' => 'Auto Repair Keeper',
        'square_access_token' => 'square-live-token',
    ]);

    app()->instance('request', $request);

    $context = app(ExceptionReporter::class)->context(new RuntimeException('Settings save failed'));

    expect($context['input']['shop_name'])->toBe('Auto Repair Keeper')
        ->and($context['input']['square_access_token'])->toBe('[redacted]');
});

test('exception reporter trace omits stack args to keep cache and archive payloads bounded', function () {
    $context = app(ExceptionReporter::class)->context(
        new RuntimeException('Square terminal payment is not completed.'),
    );

    expect($context['trace'])->toBeArray()
        ->and($context['trace'])->not->toBeEmpty();

    foreach ($context['trace'] as $frame) {
        expect($frame)->not->toHaveKey('args')
            ->and($frame)->not->toHaveKey('object');
    }
});

test('error page presenter adapts copy for portal routes', function () {
    $request = Request::create('/portal/estimate/123', 'GET');
    $page = ErrorPagePresenter::forStatus(404, $request);

    expect($page['primary_label'])->toBe('Return To Portal')
        ->and($page['surface'])->toBe('portal');
});

test('error page presenter sends technicians back to their front door', function () {
    $this->seed(ArkAuthorizationSeeder::class);
    $user = User::factory()->create()->assignRole(ArkRole::Technician->value);

    $request = Request::create('/app/repair-orders', 'GET');
    $request->setUserResolver(fn () => $user);

    $page = ErrorPagePresenter::forStatus(403, $request);

    expect($page['primary_url'])->toBe(route('operations.today'));
});

test('ad hoc php -r command line failures are not emailed', function () {
    // Throwable::getTrace() is final, so an ad-hoc CLI trace cannot be faked on
    // a real exception — assert the detection rule the notifier suppresses on.
    expect(ExceptionReporter::traceIndicatesAdHocCli([
        ['file' => 'Command line code', 'line' => 10, 'function' => 'app'],
    ]))->toBeTrue()
        ->and(ExceptionReporter::traceIndicatesAdHocCli([
            ['file' => app_path('Ark/Operations/RepairOrders/RepairOrder.php'), 'line' => 10, 'function' => 'save'],
        ]))->toBeFalse();
});

test('fatal errors from uncaught ad-hoc CLI eval are not emailed', function () {
    config([
        'errors.report.enabled' => true,
        'errors.report.email' => 'ops@example.com',
        'errors.report.queue' => false,
    ]);

    Mail::fake();

    // Symfony FatalError from uncaught tinker/php -r failures: empty getTrace(),
    // "Command line code" only in the message (see production report 94774d71).
    $fatal = new \Symfony\Component\ErrorHandler\Error\FatalError(
        "Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'v.license_plate' in 'field list'\n"
        ."Stack trace:\n"
        ."#7 Command line code(11): Illuminate\\Database\\Query\\Builder->first(Array)\n"
        ."#8 {main}\n\n"
        .'Next Illuminate\\Database\\QueryException: SQLSTATE[42S22]: Column not found',
        0,
        [
            'type' => E_ERROR,
            'message' => "Uncaught PDOException: Unknown column 'v.license_plate'",
            'file' => '/app/vendor/laravel/framework/src/Illuminate/Database/Connection.php',
            'line' => 421,
        ],
    );

    app(ExceptionReporter::class)->notify($fatal);

    Mail::assertNothingSent();
});
