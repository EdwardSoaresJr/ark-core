<?php

use App\Ark\Operations\OperationsFeatures;
use App\Ark\Operations\RepairOrders\RepairOrderEstimateConflictException;
use App\Ark\Operations\Settings\SettingsAuthorizationFailureResponse;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use App\Ark\Operations\ShopExcellence\ShopExcellenceTargets;
use App\Ark\Operations\Workstations\WorkstationPresence;
use App\Ark\Runtime\Exceptions\ExceptionReporter;
use App\Ark\Runtime\Preferences\EcosystemDisplayTheme;
use App\Http\Middleware\ApplyDevRolePretend;
use App\Http\Middleware\EnsureAdvisorCommsCleared;
use App\Http\Middleware\EnsureApiStaffActive;
use App\Http\Middleware\EnsureAppointmentsSurfaceEnabled;
use App\Http\Middleware\EnsureBusinessWorkspaceAccess;
use App\Http\Middleware\EnsureOwnerWorkspaceAccess;
use App\Http\Middleware\EnsurePasswordIsSet;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\PreventPortalSearchIndexing;
use App\Http\Middleware\RecordStaffFrontDoorLanding;
use App\Ark\Install\Middleware\RedirectUninstalledToSetup;
use App\Ark\Install\Middleware\UseInstallerRuntime;
use App\Http\Middleware\ConfigureSessionCookieDomain;
use App\Http\Middleware\RedirectCrossSurfaceRequests;
use App\Http\Middleware\SyncEcosystemDisplayThemeCookie;
use App\Http\Middleware\TrackStaffCallPresence;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            require __DIR__.'/../routes/install.php';
            // Cloud + OIDC before public — legacy catch-all must not swallow /cloud or issuer paths.
            require __DIR__.'/../routes/cloud.php';
            require __DIR__.'/../routes/cloud-ingress.php';
            require __DIR__.'/../routes/portal.php';
            require __DIR__.'/../routes/oidc.php';
            require __DIR__.'/../routes/public.php';
        },
    )
    ->withBroadcasting(
        channels: __DIR__.'/../routes/channels.php',
        attributes: ['middleware' => ['web', 'auth']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // Before StartSession — company host must not inherit Domain=.demo-auto.test.
        $middleware->prepend(ConfigureSessionCookieDomain::class);
        $middleware->prepend(RedirectCrossSurfaceRequests::class);
        // First-run: file session/cache before DB exists; redirect app traffic to /setup.
        $middleware->prepend(UseInstallerRuntime::class);
        $middleware->prependToGroup('web', RedirectUninstalledToSetup::class);

        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'oauth/token',
        ]);

        $middleware->encryptCookies(except: [
            EcosystemDisplayTheme::COOKIE_NAME,
            WorkstationPresence::BINDING_COOKIE,
            WorkstationPresence::DISMISS_COOKIE,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('portal/home', 'portal/logout', 'portal/vehicles/*')) {
                return route('portal.access');
            }

            return route('login');
        });

        $middleware->web(append: [
            ApplyDevRolePretend::class,
            EnsureUserIsActive::class,
            EnsurePasswordIsSet::class,
            // LearnArkTrainingGate retired: no shop-wide ARKademy workboard wall.
            // Presence must run before the comms gate so previous_last_seen_at is
            TrackStaffCallPresence::class,
            EnsureAdvisorCommsCleared::class,
            SyncEcosystemDisplayThemeCookie::class,
        ]);

        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'front_door' => RecordStaffFrontDoorLanding::class,
            'portal.noindex' => PreventPortalSearchIndexing::class,
            'appointments.surface' => EnsureAppointmentsSurfaceEnabled::class,
            'owner.workspace' => EnsureOwnerWorkspaceAccess::class,
            'business.workspace' => EnsureBusinessWorkspaceAccess::class,
            'api.staff' => EnsureApiStaffActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->expectsJson()
                || $request->ajax(),
        );

        $exceptions->stopIgnoring(HttpException::class);

        $exceptions->dontReport([
            RepairOrderEstimateConflictException::class,
            BroadcastException::class,
        ]);

        $exceptions->dontReportWhen(function (Throwable $exception): bool {
            if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500) {
                return true;
            }

            return false;
        });

        $exceptions->reportable(function (Throwable $exception): void {
            app(ExceptionReporter::class)->notify($exception);
        });

        $exceptions->render(function (RepairOrderEstimateConflictException $exception, Request $request) {
            return $exception->render($request);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            return SettingsAuthorizationFailureResponse::for($request);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($exception->getStatusCode() !== 403) {
                return null;
            }

            return SettingsAuthorizationFailureResponse::for($request);
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('shop-excellence:owner-digest')
            ->dailyAt(ShopExcellenceTargets::ownerDigestTime())
            ->timezone(ShopDisplayTimezone::resolve())
            ->when(fn (): bool => ShopExcellenceTargets::ownerDigestEnabled())
            ->onFailure(function (): void {
                report(new RuntimeException('Scheduled owner daily digest failed.'));
            })
            ->appendOutputTo(storage_path('logs/scheduler-owner-digest.log'));

        $schedule->command('communications:daily-coaching-digest')
            ->dailyAt(ShopExcellenceTargets::coachingDigestTime())
            ->timezone(ShopDisplayTimezone::resolve())
            ->when(fn (): bool => ShopExcellenceTargets::coachingDigestEnabled())
            ->onFailure(function (): void {
                report(new RuntimeException('Scheduled daily coaching digest failed.'));
            })
            ->appendOutputTo(storage_path('logs/scheduler-coaching-digest.log'));

        $schedule->command('comms:escalate-unhandled')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->command('time-clock:sync-auto')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        $schedule->command('comms:reconcile-stale-call-sessions')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->command('appointments:send-reminders')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->when(fn (): bool => OperationsFeatures::appointmentsEnabled());
    })->create();
