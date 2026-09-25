<?php

namespace App\Console\Commands;

use App\Ark\Website\PublishWebsiteCatalog;
use Illuminate\Console\Command;

class PublishWebsiteCatalogCommand extends Command
{
    protected $signature = 'website:publish-catalog {host?} {--force : Replace the current publication}';

    protected $description = 'Publish the imported public website catalog into Core';

    public function handle(PublishWebsiteCatalog $publisher): int
    {
        $host = $this->argument('host') ?: config('surfaces.public');
        if (! filled($host)) {
            $this->error('No public host. Pass one, or set PUBLIC_DOMAIN.');

            return self::FAILURE;
        }

        $publication = $publisher->publish((string) $host, force: (bool) $this->option('force'));
        $this->info($host.' publication version '.$publication->version.'.');

        return self::SUCCESS;
    }
}
