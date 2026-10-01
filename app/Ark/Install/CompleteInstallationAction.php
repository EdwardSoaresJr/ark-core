<?php

namespace App\Ark\Install;

use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Operations\Workstations\StoreWorkstationAction;
use App\Ark\Operations\Workstations\Workstation;
use App\Ark\Runtime\Authorization\ArkRole;
use App\Ark\Runtime\Preferences\DisplayTheme;
use App\Models\User;
use Database\Seeders\ArkAuthorizationSeeder;
use Database\Seeders\RepairOrderStatusCatalogSeeder;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class CompleteInstallationAction
{
    public function __construct(
        private readonly InstallerEnvironmentWriter $envWriter,
        private readonly DatabaseSafetyInspector $safety,
        private readonly DatabaseConnectionTester $tester,
    ) {}

    /**
     * @param  array{
     *   db: array{host: string, port: string|int, database: string, username: string, password?: string|null},
     *   app_url: string,
     *   shop: array{shop_name: string, shop_timezone: string, phone?: string|null, email?: string|null, address_line_1?: string|null, city?: string|null, state?: string|null, postal_code?: string|null},
     *   admin: array{name: string, email: string, password: string},
     *   create_workstation?: bool,
     *   skip_integrations?: bool,
     * }  $input
     * @return array{ok: bool, message: string, checkpoint?: string}
     */
    public function execute(array $input): array
    {
        if (InstallationState::isInstalled()) {
            return ['ok' => false, 'message' => 'ARK is already installed.', 'checkpoint' => 'locked'];
        }

        if (! InstallationMutex::acquire()) {
            return ['ok' => false, 'message' => 'Another installation is already in progress.', 'checkpoint' => 'mutex'];
        }

        try {
            InstallationState::markInProgress('start');
            Log::info('installer.started');

            $db = $input['db'];
            $test = $this->tester->test($db);
            if (! $test['ok']) {
                InstallationState::markFailed('database_test', $test['message']);

                return ['ok' => false, 'message' => $test['message'], 'checkpoint' => 'database_test'];
            }

            $safety = $this->safety->inspect($db);
            if (! $safety['ok']) {
                InstallationState::markFailed('database_safety', $safety['message']);

                return ['ok' => false, 'message' => $safety['message'], 'checkpoint' => 'database_safety'];
            }

            InstallationState::markInProgress('bootstrap');
            $this->persistBootstrap($input['app_url'], $db);
            $this->ensureAppKey();
            $this->reloadDatabaseConfig($db);

            InstallationState::markInProgress('migrate');
            Log::info('installer.migration_started');
            $this->pauseCompetingWorkers();
            $migrate = 1;
            try {
                $migrate = Artisan::call('migrate', ['--force' => true]);
            } finally {
                $this->resumeCompetingWorkers();
            }
            if ($migrate !== 0) {
                InstallationState::markFailed('migrate_failed', 'Database migration failed. Check server logs.');

                return ['ok' => false, 'message' => 'Database migration failed. Check server logs.', 'checkpoint' => 'migrate_failed'];
            }
            Log::info('installer.migration_completed');

            InstallationState::markInProgress('authorize');
            (new ArkAuthorizationSeeder)->run();

            InstallationState::markInProgress('status_catalog');
            $this->seedOperationalCatalogs();

            InstallationState::markInProgress('admin');
            $admin = $this->ensureAdministrator($input['admin']);
            Log::info('installer.admin_created', ['email' => $admin->email]);

            InstallationState::markInProgress('shop');
            $this->applyShop($input['shop']);

            if (($input['create_workstation'] ?? true) === true) {
                InstallationState::markInProgress('workstation');
                $this->ensureDefaultWorkstation();
            }

            InstallDraft::clear();
            InstallationIdentity::write((string) \Illuminate\Support\Str::uuid());
            RecoveryOwnerIdentity::write($input['admin']['email']);
            InstallationState::markInstalled();
            // Essential Delivery registers only after explicit ARK Platform connection.
            Log::info('installer.completed');

            try {
                Artisan::call('config:clear');
            } catch (Throwable) {
                // Non-fatal on hosts without writable bootstrap/cache.
            }

            return ['ok' => true, 'message' => 'ARK is ready.', 'checkpoint' => 'complete'];
        } catch (Throwable $e) {
            Log::error('installer.failed', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            InstallationState::markFailed('exception', 'Installation failed. Check server logs and retry.');

            return ['ok' => false, 'message' => 'Installation failed. Check server logs and retry.', 'checkpoint' => 'exception'];
        } finally {
            InstallationMutex::release();
        }
    }

    /**
     * @param  array{host: string, port: string|int, database: string, username: string, password?: string|null}  $db
     */
    private function persistBootstrap(string $appUrl, array $db): void
    {
        if ($this->envWriter->mode() !== 'writable') {
            return;
        }

        $this->envWriter->write([
            'APP_URL' => rtrim($appUrl, '/'),
            'SHOP_BASE_URL' => rtrim($appUrl, '/'),
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) ($db['port'] ?: 3306),
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => (string) ($db['password'] ?? ''),
            // Self-host / Compose must not require Redis after first run.
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
        ]);

        // Keep a copy the Compose entrypoint can restore after container recreate.
        $persist = storage_path('app/install/dotenv');
        if (is_file($this->envWriter->envPath())) {
            @mkdir(dirname($persist), 0755, true);
            @copy($this->envWriter->envPath(), $persist);
        }
    }

    private function ensureAppKey(): void
    {
        $existing = (string) config('app.key');
        if ($existing !== '' && $this->looksLikeValidKey($existing)) {
            return;
        }

        $key = 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher')));

        if ($this->envWriter->mode() === 'writable') {
            $this->envWriter->write(['APP_KEY' => $key]);
        } elseif ($existing === '') {
            throw new RuntimeException('APP_KEY is missing and the environment is not writable. Set APP_KEY in the hosting environment, then retry.');
        }

        config(['app.key' => $key]);
    }

    private function looksLikeValidKey(string $key): bool
    {
        if (! str_starts_with($key, 'base64:')) {
            return strlen($key) >= 16;
        }

        $decoded = base64_decode(substr($key, 7), true);

        return is_string($decoded) && strlen($decoded) >= 16 && ! preg_match('/^A+$/', $decoded);
    }

    /**
     * @param  array{host: string, port: string|int, database: string, username: string, password?: string|null}  $db
     */
    private function reloadDatabaseConfig(array $db): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $db['host'],
            'database.connections.mysql.port' => (string) ($db['port'] ?: 3306),
            'database.connections.mysql.database' => $db['database'],
            'database.connections.mysql.username' => $db['username'],
            'database.connections.mysql.password' => (string) ($db['password'] ?? ''),
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    private function seedOperationalCatalogs(): void
    {
        (new RepairOrderStatusCatalogSeeder)->run();
    }

    /**
     * @param  array{name: string, email: string, password: string}  $admin
     */
    private function ensureAdministrator(array $admin): User
    {
        $user = User::query()->where('email', $admin['email'])->first();
        if ($user === null) {
            $user = new User;
            $user->email = $admin['email'];
            $user->password = Hash::make($admin['password']);
        }

        $user->name = $admin['name'];
        $user->is_active = true;
        $user->is_master_admin = true;
        $user->password_set_at = now();
        // Trusted first-run bootstrap: mail is optional during setup, so this
        // administrator must not be blocked by MustVerifyEmail after install.
        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
        }
        // Fresh install default appearance is light (dark mode is incomplete).
        // Do not overwrite an already-persisted explicit preference.
        if ($user->display_theme === null || $user->display_theme === '') {
            $user->display_theme = DisplayTheme::default()->value;
        }
        if (! $user->exists) {
            $user->password = Hash::make($admin['password']);
        }
        $user->save();

        if (! $user->hasRole(ArkRole::Admin->value)) {
            $user->assignRole(ArkRole::Admin->value);
        }

        return $user;
    }

    /**
     * @param  array{shop_name: string, shop_timezone: string, phone?: string|null, email?: string|null, address_line_1?: string|null, city?: string|null, state?: string|null, postal_code?: string|null}  $shop
     */
    private function applyShop(array $shop): void
    {
        $settings = ShopSettings::current();
        $settings->fill([
            'shop_name' => $shop['shop_name'],
            'shop_timezone' => $shop['shop_timezone'],
            'phone' => $shop['phone'] ?? null,
            'email' => $shop['email'] ?? null,
            'address_line_1' => $shop['address_line_1'] ?? null,
            'city' => $shop['city'] ?? null,
            'state' => $shop['state'] ?? null,
            'postal_code' => $shop['postal_code'] ?? null,
        ])->save();
        ShopSettings::forgetCurrent();
    }

    private function ensureDefaultWorkstation(): void
    {
        if (Workstation::query()->exists()) {
            return;
        }

        app(StoreWorkstationAction::class)->execute('Main Shop', null, true);
    }

    /**
     * Free RAM on small VPS hosts while the first migrate runs.
     * Horizon/Reverb/scheduler are not needed until install completes.
     */
    private function pauseCompetingWorkers(): void
    {
        $ctl = $this->supervisorctlBinary();
        if ($ctl === null) {
            return;
        }

        foreach (['horizon', 'reverb', 'scheduler'] as $program) {
            @exec(escapeshellarg($ctl).' stop '.escapeshellarg($program).' 2>/dev/null');
        }
    }

    private function resumeCompetingWorkers(): void
    {
        $ctl = $this->supervisorctlBinary();
        if ($ctl === null) {
            return;
        }

        foreach (['horizon', 'reverb', 'scheduler'] as $program) {
            @exec(escapeshellarg($ctl).' start '.escapeshellarg($program).' 2>/dev/null');
        }
    }

    private function supervisorctlBinary(): ?string
    {
        foreach (['/usr/bin/supervisorctl', '/usr/local/bin/supervisorctl'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }
}
