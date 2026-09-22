<?php

namespace App\Console\Commands;

use App\Ark\Install\InstallationIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallationIdentityCommand extends Command
{
    protected $signature = 'ark:installation-identity
        {action : show|write}
        {--uuid= : Installation UUID to write}';

    protected $description = 'Show or set this box installation UUID without minting a random one';

    public function handle(): int
    {
        $action = strtolower((string) $this->argument('action'));

        return match ($action) {
            'show' => $this->show(),
            'write' => $this->write(),
            default => $this->invalid($action),
        };
    }

    private function show(): int
    {
        $uuid = InstallationIdentity::read();
        $this->line($uuid ?? 'missing');

        return self::SUCCESS;
    }

    private function write(): int
    {
        $uuid = trim((string) $this->option('uuid'));
        if (! Str::isUuid($uuid)) {
            $this->error('Pass --uuid with a UUID. Do not invent a Coolify application id.');

            return self::FAILURE;
        }

        $existing = InstallationIdentity::read();
        if ($existing !== null && strcasecmp($existing, $uuid) !== 0) {
            $this->error('installation_uuid already set to '.$existing);

            return self::FAILURE;
        }

        InstallationIdentity::write($uuid);
        $this->line($uuid);

        return self::SUCCESS;
    }

    private function invalid(string $action): int
    {
        $this->error("Unknown action [{$action}]. Use show|write.");

        return self::FAILURE;
    }
}
