<?php

namespace Database\Seeders;

use App\Ark\Platform\Cluster;
use App\Ark\Platform\ClusterStatus;
use App\Ark\Platform\ClusterType;
use Illuminate\Database\Seeder;

/**
 * Development seed only — not called from DatabaseSeeder.
 *
 * php artisan db:seed --class=ClusterSeeder
 */
class ClusterSeeder extends Seeder
{
    public function run(): void
    {
        Cluster::query()->updateOrCreate(
            ['slug' => 'shared-a'],
            [
                'name' => 'Shared Cluster A',
                'type' => ClusterType::Shared,
                'status' => ClusterStatus::Healthy,
                'accepting_new_shops' => true,
                'deployment_target' => 'coolify-server-01',
                'ingress_endpoint' => 'https://shared-a.internal',
                'current_version' => null,
            ],
        );
    }
}
