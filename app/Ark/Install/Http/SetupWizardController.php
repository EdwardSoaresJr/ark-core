<?php

namespace App\Ark\Install\Http;

use App\Ark\Install\CompleteInstallationAction;
use App\Ark\Install\DatabaseConnectionTester;
use App\Ark\Install\DatabaseSafetyInspector;
use App\Ark\Install\InstallDraft;
use App\Ark\Install\InstallationState;
use App\Ark\Install\InstallerEnvironmentWriter;
use App\Ark\Install\SystemRequirementsChecker;
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

    public function database(InstallerEnvironmentWriter $envWriter): View
    {
        $draft = InstallDraft::all();

        return view('install.database', [
            'step' => 3,
            'steps' => $this->steps(),
            'envMode' => $envWriter->mode(),
            'draft' => $draft,
            'suggestedUrl' => $draft['app_url'] ?? $this->suggestedAppUrl(),
        ]);
    }

    public function testDatabase(
        Request $request,
        DatabaseConnectionTester $tester,
        DatabaseSafetyInspector $safety,
    ): RedirectResponse {
        $this->rateLimit($request, 'install-db-test');

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

        $db = [
            'host' => $data['db_host'],
            'port' => $data['db_port'],
            'database' => $data['db_database'],
            'username' => $data['db_username'],
            'password' => $data['db_password'] ?? '',
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
        ]);
        // Password only in session — never draft file.
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

        return redirect()->route('install.review');
    }

    public function review(): View|RedirectResponse
    {
        if (! $this->adminReady()) {
            return redirect()->route('install.admin');
        }

        $draft = InstallDraft::all();

        return view('install.review', [
            'step' => 7,
            'steps' => $this->steps(),
            'draft' => $draft,
            'httpWarning' => str_starts_with(strtolower((string) ($draft['app_url'] ?? '')), 'http://'),
        ]);
    }

    public function install(Request $request, CompleteInstallationAction $action): RedirectResponse
    {
        $this->rateLimit($request, 'install-finalize', 3);

        if (! $this->adminReady()) {
            return redirect()->route('install.admin');
        }

        $draft = InstallDraft::all();
        $password = (string) session('install.admin_password', '');
        $dbPassword = (string) session('install.db_password', '');

        if ($password === '') {
            return redirect()->route('install.admin')->withErrors(['password' => 'Re-enter the administrator password to continue.']);
        }

        $result = $action->execute([
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
        ]);

        session()->forget(['install.admin_password', 'install.db_password']);

        if (! $result['ok']) {
            return redirect()->route('install.review')->withErrors(['install' => $result['message']]);
        }

        return redirect()->route('install.complete');
    }

    public function complete(): View|RedirectResponse
    {
        if (! InstallationState::isInstalled()) {
            return redirect()->route('install.welcome');
        }

        return view('install.complete', [
            'step' => 8,
            'steps' => $this->steps(),
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
            ['n' => 6, 'label' => 'Integrations'],
            ['n' => 7, 'label' => 'Review'],
        ];
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
