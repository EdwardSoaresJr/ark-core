<?php

namespace App\Ark\Platform\Provisioning\Coolify;

use Illuminate\Support\Collection;

/**
 * Transport-neutral Coolify contract. No raw HTTP outside HttpCoolifyClient.
 */
interface CoolifyClient
{
    public function authenticate(): CoolifyAuthenticationResult;

    /**
     * @return Collection<int, CoolifyServer>
     */
    public function listServers(): Collection;

    /**
     * @return Collection<int, CoolifyProject>
     */
    public function listProjects(): Collection;

    /**
     * @return Collection<int, CoolifyApplication>
     */
    public function listApplications(): Collection;

    public function triggerDeployment(CoolifyDeploymentCommand $command): CoolifyDeploymentResult;

    public function deploymentStatus(string $deploymentReference): CoolifyDeploymentStatus;
}
