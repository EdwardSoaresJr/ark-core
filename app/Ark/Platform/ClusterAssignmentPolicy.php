<?php

namespace App\Ark\Platform;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Decides where a Shop lives. Does not provision infrastructure.
 * Provisioning v1 ends here — does not create ProvisioningRequest or dispatch jobs.
 *
 * @see docs/platform/cluster-assignment-authority-v1.md
 * @see docs/platform/deployment-flow-v1.md
 */
final class ClusterAssignmentPolicy
{
    /**
     * Choose a cluster and record the assignment. Updates Deployment.cluster_id.
     */
    public function assign(
        Shop $shop,
        DeploymentProfile $profile,
        ClusterAssignmentSource $source = ClusterAssignmentSource::Automatic,
        ?User $actor = null,
        ?string $reason = null,
    ): ClusterAssignment {
        $cluster = $this->chooseCluster($profile);

        return DB::transaction(function () use ($shop, $profile, $cluster, $source, $actor, $reason): ClusterAssignment {
            $deployment = Deployment::query()->firstOrCreate(
                ['shop_id' => $shop->id],
                ['profile' => $profile, 'cluster_id' => null],
            );

            $previousClusterId = $deployment->cluster_id;

            ClusterAssignment::query()
                ->where('deployment_id', $deployment->id)
                ->whereNull('superseded_at')
                ->update(['superseded_at' => now()]);

            $deployment->forceFill([
                'cluster_id' => $cluster->id,
                'profile' => $profile,
            ])->save();

            return ClusterAssignment::query()->create([
                'shop_id' => $shop->id,
                'deployment_id' => $deployment->id,
                'cluster_id' => $cluster->id,
                'profile' => $profile,
                'source' => $source,
                'reason' => $reason,
                'assigned_by_user_id' => $actor?->id,
                'previous_cluster_id' => $previousClusterId,
                'assigned_at' => now(),
                'superseded_at' => null,
            ]);
        });
    }

    public function chooseCluster(DeploymentProfile $profile): Cluster
    {
        $type = match ($profile) {
            DeploymentProfile::Shared => ClusterType::Shared,
            DeploymentProfile::Dedicated => ClusterType::Dedicated,
        };

        $cluster = Cluster::query()
            ->ofType($type)
            ->healthy()
            ->assignable()
            ->withCount('deployments')
            ->orderBy('deployments_count')
            ->orderBy('id')
            ->first();

        if ($cluster === null) {
            throw new RuntimeException(
                "No assignable {$type->label()} cluster available for profile {$profile->value}.",
            );
        }

        return $cluster;
    }
}
