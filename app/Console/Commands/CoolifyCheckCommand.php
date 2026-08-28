<?php

namespace App\Console\Commands;

use App\Ark\Platform\Provisioning\Coolify\CoolifyClient;
use App\Ark\Platform\Provisioning\Coolify\CoolifyException;
use App\Ark\Platform\Provisioning\Coolify\CoolifyMessageSanitizer;
use Illuminate\Console\Command;

/**
 * Transport verification only — never deploys or creates resources.
 */
class CoolifyCheckCommand extends Command
{
    protected $signature = 'ark:coolify:check';

    protected $description = 'Verify Coolify transport (authenticate + sanitized discovery). Does not deploy.';

    public function handle(CoolifyClient $client): int
    {
        $milestone = (int) config('ark-platform.coolify.milestone', 1);
        $enabled = (bool) config('ark-platform.coolify.enabled', false);
        $baseUrl = (string) config('ark-platform.coolify.base_url', '');

        $this->info('Coolify check');
        $this->line('enabled: '.($enabled ? 'yes' : 'no'));
        $this->line('milestone: '.$milestone);
        $this->line('base_url: '.$baseUrl);
        $this->line('token: '.(config('ark-platform.coolify.token') ? '[configured]' : '[missing]'));

        try {
            $auth = $client->authenticate();
            $this->info('authenticate: ok (teams='.$auth->teamCount.')');

            if ($milestone >= 2) {
                $servers = $client->listServers();
                $this->info('servers: '.$servers->count());
                foreach ($servers->take(5) as $server) {
                    $this->line('  - '.$server->name.' ['.$server->uuid.']');
                }
            }

            if ($milestone >= 3) {
                $projects = $client->listProjects();
                $apps = $client->listApplications();
                $this->info('projects: '.$projects->count());
                $this->info('applications: '.$apps->count());
                foreach ($apps->take(5) as $app) {
                    $this->line('  - '.$app->name.' ['.$app->uuid.']');
                }
            }
        } catch (CoolifyException $e) {
            $this->error(CoolifyMessageSanitizer::sanitize($e->getMessage()));

            return self::FAILURE;
        }

        $this->warn('No deployment was triggered.');

        return self::SUCCESS;
    }
}
