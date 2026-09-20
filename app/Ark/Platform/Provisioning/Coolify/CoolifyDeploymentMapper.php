<?php

namespace App\Ark\Platform\Provisioning\Coolify;

use App\Ark\Platform\ProvisioningRequest;
use Illuminate\Support\Collection;

/**
 * Maps platform truth → CoolifyDeploymentCommand. Adapter boundary only.
 */
final class CoolifyDeploymentMapper
{
    /**
     * @param  Collection<int, CoolifyServer>  $servers
     * @param  Collection<int, CoolifyApplication>  $applications
     */
    public function toCommand(
        ProvisioningRequest $request,
        Collection $servers,
        Collection $applications,
        string $configuredApplicationUuid,
    ): CoolifyDeploymentCommand {
        $deployment = $request->deployment;
        if ($deployment === null) {
            throw new CoolifyException('ProvisioningRequest has no Deployment.', operation: 'map');
        }

        $cluster = $deployment->cluster;
        if ($cluster === null) {
            throw new CoolifyException('Deployment has no Cluster assignment.', operation: 'map');
        }

        $target = trim((string) $cluster->deployment_target);
        if ($target === '') {
            throw new CoolifyException('Cluster deployment_target is empty.', operation: 'map');
        }

        $server = $servers->first(fn (CoolifyServer $s) => $s->matchesTarget($target));
        if ($server === null) {
            throw new CoolifyException(
                "No Coolify server matches Cluster deployment_target [{$target}].",
                operation: 'map',
            );
        }

        $appUuid = trim($configuredApplicationUuid);
        if ($appUuid === '') {
            throw new CoolifyException(
                'Coolify application UUID is not configured (COOLIFY_DEPLOY_APPLICATION_UUID).',
                operation: 'map',
            );
        }

        $application = $applications->first(
            fn (CoolifyApplication $app) => strcasecmp($app->uuid, $appUuid) === 0,
        );

        if ($application === null) {
            throw new CoolifyException(
                "Configured Coolify application [{$appUuid}] was not found.",
                operation: 'map',
            );
        }

        return new CoolifyDeploymentCommand(
            applicationUuid: $application->uuid,
            serverUuid: $server->uuid,
            deploymentTarget: $target,
        );
    }
}
