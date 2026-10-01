<?php

namespace App\Ark\Install\Http;

use App\Ark\Install\DatabaseConnectionTester;
use App\Ark\Install\DatabaseSafetyInspector;
use App\Ark\Install\InstallDraft;
use App\Ark\Install\InstallFinalizeRunner;
use App\Ark\Install\InstallStorage;
use App\Ark\Install\InstallationState;
use App\Ark\Install\InstallerEnvironmentWriter;
use App\Ark\Install\PendingInstallPayload;
use App\Ark\Install\RuntimeDatabaseConfig;
use App\Ark\Install\SystemRequirementsChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class SetupWizardController
{
    public function welcome(): View|RedirectResponse
    {
        if (InstallationState::isInstalled()) {
            return redirect()->route('login');
        }

        return view('install.welcome', [
            'step' => 1,
            'steps' => $this->steps(),
        ]);
    }

    public function system(SystemRequirementsChecker $checker): View
    {
        $checks = $checker->check();

        return view('install.system', [
            'step' => 2,
            'steps' => $this->steps(),
            'checks' => $checks,
            'blocked' => $checker->hasFailures($checks),
        ]);
    }

    public function database(
        Request $request,
        InstallerEnvironmentWriter $envWriter,
        DatabaseConnectionTester $tester,
        DatabaseSafetyInspector $safety,
    ): View {
        $draft = InstallDraft::all();
        $defaults = RuntimeDatabaseConfig::formDefaults($draft);
        $manual = $request->boolean('manual') || (bool) ($draft['db_manual'] ?? false);
        $managed = RuntimeDatabaseConfig::isManaged() && ! $manual;

        $databaseStatus = null;
        $databaseMessage = null;
        if ($managed) {
            $probe = $this->probeRuntimeDatabase($tester, $safety);
            $databaseStatus = $probe['ok'] ? 'connected' : 'failed';
            $databaseMessage = $probe['message'];
        }

        return view('install.database', [
            'step' => 3,
            'steps' => $this->steps(),
            'envMode' => $envWriter->mode(),
            'draft' => $draft,
            'defaults' => $defaults,
            'managed' => $managed,
            'runtimeManaged' => RuntimeDatabaseConfig::isManaged(),
            'databaseStatus' => $databaseStatus,
            'databaseMessage' => $databaseMessage,
            'suggestedUrl' => $draft['app_url'] ?? $this->suggestedAppUrl(),
        ]);
    }

    public function testDatabase(
        Request $request,
        DatabaseConnectionTester $tester,
        DatabaseSafetyInspector $safety,
    ): RedirectResponse {
        $this->rateLimit($request, 'install-db-test');

        $manual = $request->boolean('manual');
        $managed = RuntimeDatabaseConfig::isManaged() && ! $manual;

        if ($managed) {
            $data = $request->validate([
                'app_url' => ['required', 'url', 'max:255', 'regex:/^https?:\/\//i'],
            ]);

            if (str_starts_with(strtolower($data['app_url']), 'http://')) {
                session()->flash('install_http_warning', true);
            }

            $runtime = RuntimeDatabaseConfig::read();
            $db = [
                'host' => $runtime['host'],
                'port' => $runtime['port'],
                'database' => $runtime['database'],
                'username' => $runtime['username'],
                'password' => $runtime['password'],
            ];

            $test = $tester->test($db);
            if (! $test['ok']) {
                return back()->withInput($request->except('db_password'))->withErrors(['database' => $test['message']]);
            }

            $inspect = $safety->inspect($db);
            if (! $inspect['ok']) {
                return back()->withInput($request->except('db_password'))->withErrors(['database' => $inspect['message']]);
            }

            InstallDraft::merge([
                'app_url' => rtrim($data['app_url'], '/'),
                'db_host' => $db['host'],
                'db_port' => $db['port'],
                'db_database' => $db['database'],
                'db_username' => $db['username'],
                'db_tested' => true,
                'db_managed' => true,
                'db_manual' => false,
            ]);
            session(['install.db_password' => $db['password']]);

            return redirect()->route('install.shop')->with('status', 'Database is ready.');
        }

        $data = $request->validate([
            'app_url' => ['required', 'url', 'max:255', 'regex:/^https?:\/\//i'],
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);

        if (str_starts_with(strtolower($data['app_url']), 'http://')) {
            session()->flash('install_http_warning', true);
        }

        $identity = [
            'host' => $data['db_host'],
            'port' => $data['db_port'],
            'database' => $data['db_database'],
            'username' => $data['db_username'],
        ];

        $db = [
            ...$identity,
            'password' => RuntimeDatabaseConfig::resolvePassword(
                (string) ($data['db_password'] ?? ''),
                $identity,
            ),
        ];

        $test = $tester->test($db);
        if (! $test['ok']) {
            return back()->withInput($request->except('db_password'))->withErrors(['database' => $test['message']]);
        }

        $inspect = $safety->inspect($db);
        if (! $inspect['ok']) {
            return back()->withInput($request->except('db_password'))->withErrors(['database' => $inspect['message']]);
        }

        InstallDraft::merge([
            'app_url' => rtrim($data['app_url'], '/'),
            'db_host' => $db['host'],
            'db_port' => $db['port'],
            'db_database' => $db['database'],
            'db_username' => $db['username'],
            'db_tested' => true,
            'db_managed' => false,
            'db_manual' => true,
        ]);
        session(['install.db_password' => $db['password']]);

        return redirect()->route('install.shop')->with('status', $test['message']);
    }

    public function shop(): View|RedirectResponse
    {
        if (! $this->databaseReady()) {
            return redirect()->route('install.database');
        }

        $draft = InstallDraft::all();

        return view('install.shop', [
            'step' => 4,
            'steps' => $this->steps(),
            'draft' => $draft,
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function storeShop(Request $request): RedirectResponse
    {
        if (! $this->databaseReady()) {
            return redirect()->route('install.database');
        }

        $data = $request->validate([
            'shop_name' => ['required', 'string', 'max:120'],
            'shop_timezone' => ['required', 'timezone:all'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:60'],
            'postal_code' => ['nullable', 'string', 'max:30'],
        ]);

        InstallDraft::merge($data);

        return redirect()->route('install.admin');
    }

    public function admin(): View|RedirectResponse
    {
        if (InstallationState::isActivelyInstalling()) {
            return redirect()->route('install.progress');
        }

        if (InstallationState::hasFailedCheckpoint()) {
            InstallationState::recoverInterruptedProgress();
            PendingInstallPayload::clear();
        }

        if (! $this->databaseReady() || blank(InstallDraft::all()['shop_name'] ?? null)) {
            return redirect()->route('install.shop');
        }

        return view('install.admin', [
            'step' => 5,
            'steps' => $this->steps(),
            'draft' => InstallDraft::all(),
        ]);
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'create_workstation' => ['nullable', 'boolean'],
        ]);

        InstallDraft::merge([
            'admin_name' => $data['admin_name'],
            'admin_email' => $data['admin_email'],
            'create_workstation' => (bool) ($data['create_workstation'] ?? true),
        ]);
        session([
            'install.admin_password' => $data['password'],
        ]);

        return redirect()->route('install.integrations');
    }

    public function integrations(): View|RedirectResponse
    {
        if (! $this->adminReady()) {
            return redirect()->route('install.admin');
        }

        return view('install.integrations', [
            'step' => 6,
            'steps' => $this->steps(),
        ]);
    }

    public function skipIntegrations(): RedirectResponse
    {
        InstallDraft::merge(['integrations_skipped' => true]);
        session()->forget('install.connect_cloud_after_install');
        @unlink(InstallStorage::path('connect_cloud_after_install'));

        return redirect()->route('install.review');
    }

    public function connectIntegrations(): RedirectResponse
    {
        if (! $this->adminReady()) {
            return redirect()->route('install.admin');
        }

        InstallDraft::merge([
            'integrations_skipped' => true,
            'connect_cloud_after_install' => true,
        ]);
        session(['install.connect_cloud_after_install' => true]);
        $marker = InstallStorage::path('connect_cloud_after_install');
        @mkdir(dirname($marker), 0775, true);
        file_put_contents($marker, '1');

        return redirect()->route('install.review');
    }

    public function review(): View|RedirectResponse
    {
        if (InstallationState::isActivelyInstalling()) {
            return redirect()->route('install.progress');
        }

        if (! $this->adminReady()) {
            return redirect()->route('install.admin');
        }

        $draft = InstallDraft::all();

        return view('install.review', [
            'step' => 7,
            'steps' => $this->steps(),
            'draft' => $draft,
            'httpWarning' => str_starts_with(strtolower((string) ($draft['app_url'] ?? '')), 'http://'),
            'connectCloudAfterInstall' => (bool) session('install.connect_cloud_after_install')
                || is_file(InstallStorage::path('connect_cloud_after_install')),
        ]);
    }

    public function install(Request $request): RedirectResponse
    {
        $this->rateLimit($request, 'install-finalize', 3);

        if (InstallationState::isInstalled()) {
            return redirect()->route('install.complete');
        }

        if (InstallationState::isActivelyInstalling()) {
            return redirect()->route('install.progress');
        }

        if (InstallationState::hasFailedCheckpoint()) {
            InstallationState::recoverInterruptedProgress();
            PendingInstallPayload::clear();
        }

        if (! $this->adminReady()) {
            return redirect()->route('install.admin');
        }

        $draft = InstallDraft::all();
        $password = (string) session('install.admin_password', '');
        $dbPassword = (string) session('install.db_password', '');

        if ($password === '') {
            return redirect()->route('install.admin')->withErrors(['password' => 'Re-enter the administrator password to continue.']);
        }

        if ($dbPassword === '' && (bool) ($draft['db_managed'] ?? false)) {
            $dbPassword = RuntimeDatabaseConfig::read()['password'];
        }

        if ($dbPassword === '' && RuntimeDatabaseConfig::isManaged()) {
            $dbPassword = RuntimeDatabaseConfig::resolvePassword('', [
                'host' => (string) ($draft['db_host'] ?? ''),
                'port' => (int) ($draft['db_port'] ?? 3306),
                'database' => (string) ($draft['db_database'] ?? ''),
                'username' => (string) ($draft['db_username'] ?? ''),
            ]);
        }

        $input = [
            'db' => [
                'host' => (string) $draft['db_host'],
                'port' => (int) $draft['db_port'],
                'database' => (string) $draft['db_database'],
                'username' => (string) $draft['db_username'],
                'password' => $dbPassword,
            ],
            'app_url' => (string) $draft['app_url'],
            'shop' => [
                'shop_name' => (string) $draft['shop_name'],
                'shop_timezone' => (string) $draft['shop_timezone'],
                'phone' => $draft['phone'] ?? null,
                'email' => $draft['email'] ?? null,
                'address_line_1' => $draft['address_line_1'] ?? null,
                'city' => $draft['city'] ?? null,
                'state' => $draft['state'] ?? null,
                'postal_code' => $draft['postal_code'] ?? null,
            ],
            'admin' => [
                'name' => (string) $draft['admin_name'],
                'email' => (string) $draft['admin_email'],
                'password' => $password,
            ],
            'create_workstation' => (bool) ($draft['create_workstation'] ?? true),
            'skip_integrations' => true,
        ];

        PendingInstallPayload::write($input);
        InstallationState::markInProgress('queued');
        session()->forget(['install.admin_password', 'install.db_password']);

        InstallFinalizeRunner::start();

        return redirect()->route('install.progress');
    }

    public function progress(): View|RedirectResponse
    {
        if (InstallationState::isInstalled()) {
            return redirect()->route('install.complete');
        }

        if (! InstallationState::isInProgress() && PendingInstallPayload::read() === null) {
            return redirect()->route('install.review');
        }

        $state = InstallationState::read();

        return view('install.progress', [
            'step' => 7,
            'steps' => $this->steps(),
            'checkpoint' => $state['checkpoint'],
            'label' => InstallationState::checkpointLabel($state['checkpoint']),
            'failed' => InstallationState::hasFailedCheckpoint(),
            'errorMessage' => InstallationState::failureMessage(),
            'statusUrl' => route('install.progress.status'),
            'completeUrl' => route('install.complete'),
            'reviewUrl' => route('install.review'),
        ]);
    }

    public function progressStatus(): JsonResponse
    {
        if (InstallationState::isInstalled()) {
            return response()->json([
                'phase' => 'complete',
                'status' => InstallationState::INSTALLED,
                'checkpoint' => 'complete',
                'label' => InstallationState::checkpointLabel('complete'),
                'message' => null,
                'complete_url' => route('install.complete'),
            ]);
        }

        $state = InstallationState::read();
        $failed = InstallationState::hasFailedCheckpoint();

        if (! InstallationState::isInProgress()) {
            return response()->json([
                'phase' => 'idle',
                'status' => $state['status'],
                'checkpoint' => $state['checkpoint'],
                'label' => 'Installation has not started.',
                'message' => null,
                'review_url' => route('install.review'),
                'complete_url' => route('install.complete'),
            ]);
        }

        return response()->json([
            'phase' => $failed ? 'failed' : 'running',
            'status' => $state['status'],
            'checkpoint' => $state['checkpoint'],
            'label' => InstallationState::checkpointLabel($state['checkpoint']),
            'message' => $failed
                ? (InstallationState::failureMessage() ?? 'Installation failed. You can try again from Review.')
                : null,
            'review_url' => route('install.review'),
            'complete_url' => route('install.complete'),
        ]);
    }

    public function complete(): View|RedirectResponse
    {
        if (! InstallationState::isInstalled()) {
            if (InstallationState::isActivelyInstalling()) {
                return redirect()->route('install.progress');
            }

            return redirect()->route('install.welcome');
        }

        return view('install.complete', [
            'step' => 8,
            'steps' => $this->steps(),
            'connectCloudAfterInstall' => (bool) session('install.connect_cloud_after_install')
                || is_file(InstallStorage::path('connect_cloud_after_install')),
        ]);
    }

    /**
     * @return list<array{n: int, label: string}>
     */
    private function steps(): array
    {
        return [
            ['n' => 1, 'label' => 'Welcome'],
            ['n' => 2, 'label' => 'System'],
            ['n' => 3, 'label' => 'Database'],
            ['n' => 4, 'label' => 'Shop'],
            ['n' => 5, 'label' => 'Admin'],
            ['n' => 6, 'label' => 'ARK Platform'],
            ['n' => 7, 'label' => 'Review'],
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function probeRuntimeDatabase(
        DatabaseConnectionTester $tester,
        DatabaseSafetyInspector $safety,
    ): array {
        $runtime = RuntimeDatabaseConfig::read();
        $db = [
            'host' => $runtime['host'],
            'port' => $runtime['port'],
            'database' => $runtime['database'],
            'username' => $runtime['username'],
            'password' => $runtime['password'],
        ];

        $test = $tester->test($db);
        if (! $test['ok']) {
            return $test;
        }

        $inspect = $safety->inspect($db);
        if (! $inspect['ok']) {
            return ['ok' => false, 'message' => $inspect['message']];
        }

        return ['ok' => true, 'message' => 'Database is ready.'];
    }

    private function databaseReady(): bool
    {
        $draft = InstallDraft::all();

        return (bool) ($draft['db_tested'] ?? false);
    }

    private function adminReady(): bool
    {
        $draft = InstallDraft::all();

        return $this->databaseReady()
            && filled($draft['shop_name'] ?? null)
            && filled($draft['admin_email'] ?? null)
            && filled(session('install.admin_password'));
    }

    private function suggestedAppUrl(): string
    {
        $scheme = request()->isSecure() ? 'https' : 'http';
        $host = request()->getHttpHost();

        return $scheme.'://'.$host;
    }

    private function rateLimit(Request $request, string $key, int $max = 10): void
    {
        $limiterKey = $key.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($limiterKey, $max)) {
            abort(429, 'Too many attempts. Wait and try again.');
        }
        RateLimiter::hit($limiterKey, 60);
    }
}
